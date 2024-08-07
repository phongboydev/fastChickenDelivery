<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\PeriodHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * Class WorkSchedule
 * @package App\Models
 * @property Carbon schedule_date
 * @property String check_in
 * @property String check_out
 * @property String rest_hours
 * @property bool is_holiday
 * @property bool is_off_day
 */
class WorkSchedule extends Model
{

    use UsesUuid, HasAssignment;

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'schedule_date',
        'check_in',
        'check_out',
        'start_break',
        'end_break',
        'rest_hours',
        'is_holiday',
        'is_off_day',
        'work_schedule_group_id',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'schedule_date',
    ];

    protected $casts = [
        'schedule_date'  => 'date:Y-m-d',
    ];

    /**
     * Get shift.
     *
     * @return float
     */
    public function getShiftAttribute(): float
    {
        return $this->attributes['shift'] ?? 0;
    }

    /**
     * Set shift.
     *
     * @param  float | null $value
     * @return void
     */
    public function setShiftAttribute(float $value = null)
    {
        if ($value) {
            $this->attributes['shift'] = $value;
        }
    }

    /**
     * Generate work schedules for month begin from $beginDate
     *
     * @param Carbon $beginDate
     * @param Carbon $endDate
     * @param array  $workDays
     * @param string $checkIn
     * @param string $checkOut
     * @param string $restHours
     *
     * @param string $workScheduleGroupId
     *
     * @return \Illuminate\Support\Collection
     */
    public function generateMonthWorkSchedules(
        Carbon $beginDate,
        Carbon $endDate,
        array $workDays,
        string $checkIn,
        string $checkOut,
        string $restHours,
        string $startBreak,
        string $endBreak,
        string $workScheduleGroupId
    ) {
        if (!$beginDate->isBefore($endDate)) {
            // Swap
            $tmp = $beginDate;
            $beginDate = $endDate;
            $endDate = $tmp;
        }

        $holidays = YearHoliday::select('day')->whereBetween('day', [$beginDate->format('Y-m-d'), $endDate->format('Y-m-d')])->get()->toArray();

        $holidayCollection = collect($holidays);
        $workDayCollection = collect($workDays);

        $days = collect();
        $currentDate = $beginDate->copy();
        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $ws = new WorkSchedule();
            $ws->schedule_date = $currentDate->copy();
            if ($workDayCollection->contains($currentDate->dayOfWeek)) {
                $ws->check_in = $checkIn;
                $ws->check_out = $checkOut;
                $ws->rest_hours = $restHours;
                $ws->start_break = $startBreak;
                $ws->end_break = $endBreak;
                $ws->is_off_day = false;
                $ws->is_holiday = false;
            } else {
                $ws->check_in = "";
                $ws->check_out = "";
                $ws->rest_hours = "";
                $ws->start_break = "";
                $ws->end_break = "";
                $ws->is_off_day = true;
                $ws->is_holiday = false;
            }

            if ($holidayCollection->flatten()->contains($currentDate->format('Y-m-d'))) {
                $ws->is_holiday = true;
            }

            $ws->work_schedule_group_id = $workScheduleGroupId;
            $days->push($ws);
            $currentDate->addDay();
        }
        return $days;
    }

    public function checkExitWorkSchedule($client_id, $schedule_date)
    {
        return $this->where("client_id", $client_id)->where("schedule_date", $schedule_date)->exists();
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function workScheduleGroup()
    {
        return $this->belongsTo(WorkScheduleGroup::class);
    }

    /**
     * Get all of the post's comments.
     */
    public function approves()
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    /**
     * @param self $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where('client_id', '=', $user->client_id);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function getWorkSchedulePeriodAttribute(): Period
    {
        if ($this->is_off_day) {
            return Period::make(
                $this->schedule_date_string . ' 00:00:00',
                $this->schedule_date_string . ' 00:00:01',
                Precision::SECOND
            );
        }

        // Schedule period
        $scheduleEndOnNextDay = $this->next_day;
        $wsStart = $this->schedule_date_string . ' ' . $this->check_in . ':00';
        if ($scheduleEndOnNextDay) {
            $nextLogDate = \Carbon\Carbon::parse($this->schedule_date_string)->addDay()->format('Y-m-d');
            $wsEnd = $nextLogDate . ' ' . $this->check_out . ':00';
        } else {
            $wsEnd = $this->schedule_date_string . ' ' . $this->check_out . ':00';
        }
        $wsPeriod = Period::make($wsStart, $wsEnd, Precision::SECOND);
        return $wsPeriod;
    }

    public function getRestPeriodAttribute(): Period
    {
        try {
            if ($this->is_off_day) {
                return PeriodHelper::makePeriod(
                    $this->schedule_date_string . ' 00:00:00',
                    $this->schedule_date_string . ' 00:00:01'
                );
            }
            if ($this->start_break === '00:00' && $this->end_break === '00:00') {
                return PeriodHelper::makePeriod(
                    $this->schedule_date_string . ' 00:00:00',
                    $this->schedule_date_string . ' 00:00:01'
                );
            }
            $breakIn = Carbon::parse($this->schedule_date_string . ' ' . $this->start_break . ':00');
            if ($this->start_break < $this->check_in && $this->next_day) {
                $breakIn = $breakIn->addDay();
            }
            $breakOut = Carbon::parse($this->schedule_date_string . ' ' . $this->end_break . ':00');
            if ($this->end_break < $this->check_out && $this->next_day) {
                $breakOut = $breakOut->addDay();
            }
            return PeriodHelper::makePeriod($breakIn, $breakOut);
        } catch (Exception $e) {
            logger()->debug('WorkSchedule@getRestPeriodAttribute invalid break time', [
                'start' => $this->start_break,
                'end' => $this->end_break,
            ]);
        }

        return PeriodHelper::makePeriod(
            $this->schedule_date_string . ' 00:00:00',
            $this->schedule_date_string . ' 00:00:01'
        );
    }

    public function getWorkHoursAttribute()
    {
        if ($this->is_off_day) {
            return 0;
        }
        return $this->getWorkHoursWithoutCheckAttribute();
    }

    public function getWorkHoursWithoutCheckAttribute()
    {
        // try to get work hours don't care if it is off day, holiday, or without check in/out
        try {
            $checkIn = Carbon::parse($this->schedule_date_string . ' ' . $this->check_in . ':00');
            $checkOut = Carbon::parse($this->schedule_date_string . ' ' . $this->check_out . ':00');
            if ($this->next_day) {
                $checkOut->addDay();
            }
            $period = PeriodHelper::makePeriod($checkIn, $checkOut);
            $restPeriod = $this->getRestPeriodAttribute();
            if ($period->overlapsWith($restPeriod)) {
                $collection = $period->diff($restPeriod);
                $sum = 0.0;
                foreach ($collection as $item) {
                    $sum += PeriodHelper::countHours($item);
                }
                return $sum;
            }
            return PeriodHelper::countHours($period);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }

    /**
     * @return string
     */
    public function getScheduleDateStringAttribute()
    {
        return $this->schedule_date->format('Y-m-d');
    }

    /**
     * Dummy next_day field
     * Future: Replace with real field in DB to store next day's work schedule
     *
     * @return int|mixed
     */
    public function getNextDayAttribute()
    {
        return $this->attributes['next_day'] ?? 0;
    }

    public function setNextDayAttribute($value)
    {
        return $this->attributes['next_day'] = $value;
    }

    public function getCoreTimeInAttribute()
    {
        $workScheduleGroupTemplate = $this->workScheduleGroup->workScheduleGroupTemplate;
        if ($workScheduleGroupTemplate) {
            return $workScheduleGroupTemplate->core_time_in;
        }
        return null;
    }

    public function getShiftEnableAttribute()
    {
        return  $this->attributes['shift_enabled'] ?? false;
    }

    public function setShiftEnableAttribute($value)
    {
        $this->attributes['shift_enabled'] = $value;
    }
}
