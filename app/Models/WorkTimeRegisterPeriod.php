<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\Support\WorkTimeRegisterPeriodHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

/**
 * @property string $id
 * @property string $worktime_register_id
 * @property string $date_time_register
 * @property string $type_register
 * @property float $start_time
 * @property float $end_time
 * @property float $start_break
 * @property float $end_break
 * @property boolean $start_break_next_day
 * @property boolean $end_break_next_day
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property-read string $duration
 * @property WorktimeRegister $worktimeRegister
 */
class WorkTimeRegisterPeriod extends Model
{

    const TYPE_ALL_DAY = 0;
    const TYPE_BY_HOUR = 1;

    use UsesUuid, LogsActivity, HasAssignment, HasFactory, SoftDeletes;

    protected static $logAttributes = ['*'];
    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'work_time_register_periods';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    /**
     * @var array
     */
    protected $fillable = [
        'worktime_register_id',
        'date_time_register',
        'type_register',
        'start_time',
        'end_time',
        'start_break',
        'end_break',
        'start_break_next_day',
        'end_break_next_day',
        'has_fee',
        'so_gio_tam_tinh',
        'da_tru',
        'deduction_details',
        'logical_management',
        'next_day',
        'change_flexible_checkin'
    ];

    /**
     * @return BelongsTo
     */
    public function worktimeRegister(): BelongsTo
    {
        return $this->belongsTo('App\Models\WorktimeRegister');
    }

    public function setStartBreakAttribute($value)
    {
        if ($value) {
            $this->attributes['start_break'] = Carbon::parse($value)->format('H:i');
        }
    }

    public function setEndBreakAttribute($value)
    {
        if ($value) {
            $this->attributes['end_break'] = Carbon::parse($value)->format('H:i');
        }
    }

    public function getDurationAttribute()
    {
        $diffHours = 0;
        $wtrPeriod = $this->getPeriod();
        $date = $this->date_time_register;
        $wtr = $this->worktimeRegister;
        $employee = $wtr->clientEmployee;
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
        $workSchedule = $employee->getWorkSchedule($date);

        if (!$workSchedule) return 0;

        // Check type is leave or business
        if (in_array($wtr->type, [Constant::TYPE_LEAVE, Constant::TYPE_BUSINESS])) {

            // Check with the off day or holiday
            if ($workSchedule->is_off_day || $workSchedule->is_holiday) return 0;

            // Check with multiple shift
            if ($workSchedule->shift_enabled && $clientWorkFlowSetting->enable_multiple_shift) {
                $ts = Timesheet::where('client_employee_id', $employee->id)->whereDate('log_date', $date)->first();
                if ($ts) {
                    $shiftMapping = $ts->timesheetShiftMapping;
                    foreach ($shiftMapping as $item) {
                        if ($this->type_register == self::TYPE_ALL_DAY) {
                            $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                            $diffHours += $schedulePeriodsWithoutRest->reduce(function ($carry, $period) {
                                $carry += PeriodHelper::countHours($period);
                                return $carry;
                            }, 0);
                        } else {
                            $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                            $overlap = $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($wtrPeriod));
                            $diffHours += $overlap->reduce(function ($carry, $period) {
                                $carry += PeriodHelper::countHours($period);
                                return $carry;
                            }, 0);
                        }
                    }
                }

            // Check with work schedule
            } else {
                // Override work schedule with not approved status
                $flexibleCheckIn = $this->change_flexible_checkin;
                if($employee->timesheet_exception == Constant::TYPE_FLEXIBLE_TIMESHEET && !$workSchedule->shift_enabled && !is_null($flexibleCheckIn)) {
                    $workTemplate = $employee->workScheduleGroupTemplate;
                    $wtr->realStatus = true;
                    if($wtr->status != Constant::APPROVE_STATUS) {
                        $workSchedule->check_in = $flexibleCheckIn;
                        $workSchedule->check_out = WorkTimeRegisterPeriodHelper::calculationFlexibleCheckout($flexibleCheckIn, $workTemplate);
                    }
                }

                // Prepare variable
                $restPeriod = $workSchedule->getRestPeriodAttribute();
                $diffHourLeave = 0;
                $workPeriod = $workSchedule->getWorkSchedulePeriodAttribute();

                // Only business for old data and mobile
                // In the future can remove because the application is not the same time.
                if ($wtr->type == Constant::TYPE_BUSINESS) {
                    if ($workSchedule->leave_hours || $this->run_overlap_leave) {
                        WorkTimeRegisterPeriod::where('date_time_register', $date)
                            ->whereHas('worktimeRegister', function ($query) use ($employee) {
                                $query->where('type', 'leave_request')
                                    ->where("status", "approved")
                                    ->where("client_employee_id", $employee->id);
                            })
                            ->chunkById(10, function ($items) use (&$diffHourLeave, $workPeriod, $restPeriod, $wtrPeriod) {
                                foreach ($items as $item) {
                                    $period = $item->getPeriod();
                                    $period = $period->overlapSingle($wtrPeriod);
                                    if (!$period) {
                                        continue;
                                    }
                                    $periodLeave = $period->overlapSingle($workPeriod);
                                    if ($periodLeave) {
                                        $overlapWithRest = $restPeriod->overlapSingle($periodLeave);
                                        $restMinute = empty($overlapWithRest) ? 0 : PeriodHelper::countMinutes($overlapWithRest);
                                        $diffHourLeave += round((PeriodHelper::countMinutes($periodLeave) - $restMinute) / 60, 4);
                                    }
                                }
                            });
                    }
                }

                // Calculate hours by all day
                if ($this->type_register == self::TYPE_ALL_DAY) {
                    // workHours of schedule already deduct rest period
                    $diffHours = $workSchedule->getWorkHoursAttribute();
                } else {
                // Calculate hours by hours
                    $workPeriod = $workSchedule->getWorkSchedulePeriodAttribute();
                    // Only the portion that intersects with the work schedule is counted
                    $overlapWithWork = $workPeriod->overlapSingle($wtrPeriod);
                    if (empty($overlapWithWork)) return 0;

                    $overlapWithRest = $restPeriod->overlapSingle($overlapWithWork);
                    $restMinute = empty($overlapWithRest) ? 0 : PeriodHelper::countMinutes($overlapWithRest);
                    $diffHours += round((PeriodHelper::countMinutes($overlapWithWork) - $restMinute) / 60, 4);
                }

                // Sub $diffHourLeave (leave application the same time with business application)
                if ($diffHourLeave > 0) {
                    $diffHours -= $diffHourLeave;
                }
            }
        } else {
            // OT requests
            $diffHours = PeriodHelper::countHours($wtrPeriod) - PeriodHelper::countHours($this->break_period);
        }
        return round($diffHours, 2);
    }

    public function getDurationForLeaveRequestAttribute()
    {
        $wtrPeriod = $this->getPeriod();
        $date = $this->date_time_register;
        $diffHours = 0;
        $employee = $this->worktimeRegister->clientEmployee;
        $wtr = $this->worktimeRegister;
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
        $ts = Timesheet::where('client_employee_id', $employee->id)->whereDate('log_date', $date)->first();
        if ($wtr->type == 'leave_request') {
            if ($ts && $ts->isUsingMultiShift($clientWorkFlowSetting)) {
                $shiftMapping = $ts->timesheetShiftMapping;
                foreach ($shiftMapping as $item) {
                    $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                    if ($this->type_register == self::TYPE_ALL_DAY) {
                        $overlap = $schedulePeriodsWithoutRest;
                    } else {
                        $overlap = $schedulePeriodsWithoutRest->overlapSingle(new PeriodCollection($wtrPeriod));
                    }
                    $diffHours += $overlap->reduce(function ($carry, $period) {
                        $carry += round((PeriodHelper::countHours($period)) / 60, 4);
                        return $carry;
                    }, 0);
                }
            } else {
                $workSchedule = $employee->getWorkSchedule($date);
                if (!$workSchedule) return 0; // Temporary fix work schedule is null
                $restPeriod = $workSchedule->getRestPeriodAttribute();
                if ($this->type_register == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                    // workHours of schedule already deduct rest period
                    $diffHours = $workSchedule->getWorkHoursAttribute();
                } else {
                    $diffHours = PeriodHelper::countHours($wtrPeriod);
                    $overlap = $restPeriod->overlapSingle($wtrPeriod);
                    if ($overlap) {
                        $overlapDuration = PeriodHelper::countHours($overlap);
                        $diffHours -= $overlapDuration;
                    }
                    $workPeriod = $workSchedule->getWorkSchedulePeriodAttribute();
                    $overlapOutWorkingTime = $workPeriod->overlapSingle($wtrPeriod);
                    if ($overlapOutWorkingTime) {
                        $diffHours -= PeriodHelper::countHours($wtrPeriod) - PeriodHelper::countHours($overlapOutWorkingTime);
                    }
                }
            }
        }

        return round($diffHours, 2);
    }

    public function getPeriod(): Period
    {
        return Period::make($this->getStartDatetimeAttribute(), $this->getEndDatetimeAttribute(), Precision::SECOND);
    }

    public function getStartDatetimeAttribute(): string
    {
        return $this->date_time_register . ($this->start_time && $this->type_register != WorkTimeRegisterPeriod::TYPE_ALL_DAY ? (" " . $this->start_time) : " 00:00:00");
    }

    public function getEndDatetimeAttribute(): string
    {
        if ($this->next_day) {
            $datetimeRegister = Carbon::parse($this->date_time_register)->addDays()->format('Y-m-d');

            return $datetimeRegister . ' ' . $this->end_time;
        } else {
            return $this->date_time_register . ($this->end_time && $this->type_register != WorkTimeRegisterPeriod::TYPE_ALL_DAY ? (" " . $this->end_time) : " 23:59:59");
        }
    }

    public function getBreakPeriodAttribute(): Period
    {
        return Period::make($this->start_break_datetime, $this->end_break_datetime, Precision::SECOND);
    }

    public function getStartBreakDatetimeAttribute(): string
    {
        if (!$this->start_break) {
            return "2021-01-01 00:00:00";
        }

        if ($this->start_break_next_day) {
            $datetimeRegister = Carbon::parse($this->date_time_register)->addDays()->format('Y-m-d');
        } else {
            $datetimeRegister = $this->date_time_register;
        }

        return $datetimeRegister . " " . $this->start_break;
    }

    public function getEndBreakDatetimeAttribute(): string
    {
        if (!$this->end_break) {
            return "2021-01-01 00:00:01";
        }

        if ($this->end_break_next_day) {
            $datetimeRegister = Carbon::parse($this->date_time_register)->addDays()->format('Y-m-d');
        } else {
            $datetimeRegister = $this->date_time_register;
        }

        return $datetimeRegister . " " . $this->end_break;
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

    public static function getEstimatedTotalTime($clientEmployeeId, $type, $sub_type, $category)
    {
        return WorkTimeRegisterPeriod::whereHas('worktimeRegister', function ($query) use ($clientEmployeeId, $type, $sub_type, $category) {
            $query->where('client_employee_id', $clientEmployeeId)
                ->where('type', $type)
                ->where('sub_type', $sub_type)
                ->where('category', $category)
                ->whereNotIn('status', ['canceled', 'canceled_approved']);
        })->where('da_tru', false)->sum('so_gio_tam_tinh') ?? 0;
    }

    public static function getEstimatedTotalYearLeaveTime($clientEmployeeId, $mode = false)
    {
        $type = Constant::TYPE_LEAVE;
        $sub_type = Constant::AUTHORIZED_LEAVE;
        $category = 'year_leave';

        $clientEmployee = ClientEmployee::find($clientEmployeeId);
        $query = WorkTimeRegisterPeriod::whereHas('worktimeRegister', function ($query) use ($clientEmployeeId, $type, $sub_type, $category) {
            $query->where('client_employee_id', $clientEmployeeId)
                ->where('type', $type)
                ->where('sub_type', $sub_type)
                ->where('category', $category)
                ->whereNotIn('status', ['canceled', 'canceled_approved']);
        })->where('da_tru', false);

        if ($mode) {
            $nextYearPaidLeaveStart = $clientEmployee->next_year_paid_leave_start ?? 0;
            $nextYearPaidLeaveExpiry = $clientEmployee->next_year_paid_leave_expiry ?? 0;

            if ($nextYearPaidLeaveStart === null || $nextYearPaidLeaveExpiry === null) {
                return 0;
            }

            // Tổng giờ năm sau
            $query->where('date_time_register', '>=', $nextYearPaidLeaveStart)
                ->where('date_time_register', '<=', $nextYearPaidLeaveExpiry);
        } else {
            $yearPaidLeaveExpiry = $clientEmployee->year_paid_leave_expiry;
            // Tổng giờ năm nay
            $query->where('date_time_register', '<=', $yearPaidLeaveExpiry);
        }

        return $query->sum('so_gio_tam_tinh') ?? 0;
    }

    public function setRunOverlapLeaveAttribute($value)
    {
        $this->attributes['run_overlap_leave'] = true;
    }

    public function getRunOverlapLeaveAttribute()
    {
        return $this->attributes['run_overlap_leave'] ?? false;
    }

    public function getStatusAttribute()
    {
        if ($this->is_cancellation_approval_pending) {
            return Constant::WAIT_CANCEL_APPROVE;
        } else {
            $wtr = $this->worktimeRegister;
            $wtr->realStatus = true;
            return $wtr->status;
        }
    }

    public function getDeductionLastYearAttribute()
    {
        if ($this->logical_management && !empty($this->deduction_details)) {
            $deductionDetails = json_decode($this->deduction_details, true);
            return $deductionDetails['last_year'] ?? 0;
        } else {
            return 0;
        }
    }

    public function getDeductionCurrentYearAttribute()
    {
        if ($this->logical_management && !empty($this->deduction_details)) {
            $deductionDetails = json_decode($this->deduction_details, true);
            return $deductionDetails['current_year'] ?? 0;
        } else {
            return 0;
        }
    }

    public function getDeductioNextYearAttribute()
    {
        if ($this->logical_management && !empty($this->deduction_details)) {
            $deductionDetails = json_decode($this->deduction_details, true);
            return $deductionDetails['next_year'] ?? 0;
        } else {
            return 0;
        }
    }
}
