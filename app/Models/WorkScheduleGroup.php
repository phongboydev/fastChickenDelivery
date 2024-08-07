<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class WorkSchedule
 * @package App\Models
 * @property Carbon timesheet_from
 * @property Carbon timesheet_to
 * @property Carbon timeseet_deadline_at
 */
class WorkScheduleGroup extends Model
{

    use UsesUuid, HasAssignment, LogsActivity;

    /**
     * @var array|string[]
     */
    protected static array $logAttributes = ['*'];

    public $timestamps = false;
    protected $fillable = [
        'work_schedule_group_template_id',
        'client_id',
        'name',
        'timesheet_from',
        'timesheet_to',
        'timesheet_deadline_at',
        'approve_deadline_at',
    ];

    protected $dates = [
      'timesheet_from',
      'timesheet_to',
      'timeseet_deadline_at',
      'approve_deadline_at'
    ];

    /**
     * @return BelongsTo
     */
    public function workScheduleGroupTemplate()
    {
        return $this->belongsTo('App\Models\WorkScheduleGroupTemplate');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function calculateExpectedWorkHours( $work_schedule_group_id ) {

        /** @var Collection|Timesheet[] $timesheets */
        $workSchedules = WorkSchedule::query()
                                     ->where('work_schedule_group_id', $work_schedule_group_id)
                                     ->get()
                                     ->keyBy(function (WorkSchedule $ws) { return $ws->schedule_date->toDateString(); });
        logger("WorkScheduleObserver::loadSysteVariables Work schedules", [$workSchedules]);

        $workScheduleGroupTemplate = WorkScheduleGroupTemplate::where('id', $this->work_schedule_group_template_id)->first();

        // Tmp tính số giờ làm việc của mỗi ngày trong lịch làm việc
        $workScheduleHours = collect();
        $workSchedules->each(function (WorkSchedule $item) use ($workScheduleHours, $workScheduleGroupTemplate) {
            // Ngày nghỉ work hours = 0
            if ($item->is_off_day) {
                $workScheduleHours->put($item->schedule_date->toDateString(), 0);
                return;
            }

            $checkedIn = $item->check_in;
            $checkedOut = $item->check_out;

            if ($item->is_holiday) {

                if(!$checkedIn || !$checkedOut){
                    $checkedIn  = $workScheduleGroupTemplate->check_in;
                    $checkedOut = $workScheduleGroupTemplate->check_out;
                }
            }

            // Ngày đi làm, tính số giờ làm từ check_in đến check_out, trừ cho rest_hours
            $hms = explode(':', $checkedIn);
            if (!isset($hms[1]) || !is_numeric($hms[0]) || !is_numeric($hms[1])) {
                logger("WorkScheduleObserver::loadSysteVariables illegal check_in hour",
                    [$item]);
                return;
            }
            $checkIn = Carbon::today()->setHour(intval($hms[0]))->setMinute(intval($hms[1]))->setSecond(0);
            $hms = explode(':', $checkedOut);
            if (!isset($hms[1]) || !is_numeric($hms[0]) || !is_numeric($hms[1])) {
                logger("WorkScheduleObserver::loadSysteVariables illegal check_out hour",
                    [$item]);
                return;
            }
            $checkOut = Carbon::today()->setHour(intval($hms[0]))->setMinute(intval($hms[1]))->setSecond(0);

            $hms = explode(':', $item->rest_hours);
            if (!isset($hms[1]) || !is_numeric($hms[0]) || !is_numeric($hms[1])) {
                logger("WorkScheduleObserver::loadSysteVariables illegal rest_hours hour",
                    [$item]);
                $restHours = 0;
            } else {
                $restHours =  floatval($hms[0]) + (floatval($hms[1])/60);
            }
            $workScheduleHours->put($item->schedule_date->toDateString(), $checkIn->diffAsCarbonInterval($checkOut)->totalHours - $restHours);
        });
        logger("WorkScheduleObserver::loadSysteVariables work schedule hours array",
            [$workScheduleHours]);

        // Số giờ làm việc chuẩn của công ty trong tháng này
        $expectedWorkHours = $workScheduleHours->sum();
        logger("WorkScheduleObserver::loadSysteVariables expected work hours of company",
            [$expectedWorkHours]);

        return $expectedWorkHours;
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
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
