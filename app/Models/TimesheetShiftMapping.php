<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\PeriodHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 *
 *
 */
class TimesheetShiftMapping extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    private $PrecisionType = Precision::SECOND;
    private $BoundariesType = Boundaries::EXCLUDE_NONE;
    protected $timesheetMinBlock = 1;

//    protected $casts = [
//        'converted_check_in',
//        'converted_check_out',
//        'rest_shift_period',
//        'schedule_shift_without_rest',
//        'schedule_shift_period',
//        'actual_in_out_period',
//        'block_in_out_period',
//        'rest_shift_hours',
//        'schedule_shift_hours',
//        'shift_check_in',
//        'shift_check_out',
//        'shift_next_day',
//        'shift_is_flexible'
//    ];

    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'timesheet_shift_mapping';
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
        'timesheet_id',
        'timesheet_shift_id',
        'check_in',
        'check_out',
        'shift',
        'working_hours',
        'rest_hours',
        'skip_hanet'
    ];

    /**
     * @return BelongsTo
     */
    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class, 'timesheet_id');
    }

    /**
     * @return BelongsTo
     */
    public function viewCombinedTimesheet()
    {
        return $this->belongsTo(ViewCombinedTimesheet::class, 'timesheet_id', 'timesheet_id');
    }

    /**
     * @return BelongsTo
     */
    public function timesheetShift()
    {
        return $this->belongsTo(TimesheetShift::class, 'timesheet_shift_id');
    }

    public function approves(): MorphMany
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function setPrecisionTypeAttribute($type)
    {
        $this->PrecisionType = $type;
    }

    public function setBoundariesTypeAttribute($type)
    {
        $this->BoundariesType = $type;
    }

    public function getConvertedCheckInAttribute()
    {
        if (!$this->check_in) {
            return $this->check_in;
        }

        $actual_in = Carbon::parse($this->check_in);
        $schedule_in = Carbon::parse($this->timesheet->log_date . ' ' . $this->shift_check_in);

        /**
         * Case 1: Check in before schedule_in or don't meet core_shift condition
         * Return check in.
         */
        if (
            $actual_in->isAfter($schedule_in)
            && $this->applied_shift_type == TimesheetShift::CORE_SHIFT
        ) {
            $core_time_in = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->core_time_in);
            $compromised_minutes = $core_time_in->diffInMinutes($schedule_in);
            /**
             * Case 2: Check in before core-time
             * Return check in which equal core-time.
             */
            if ($actual_in->lessThanOrEqualTo($core_time_in)) {
                return $schedule_in->format('Y-m-d H:i:s');
            }

            if ($this->timesheetShift->break_start && $this->timesheetShift->break_end) {
                $break_start = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->break_start);
                if ($this->timesheetShift->next_day_break_start) {
                    $break_start->addDay();
                }

                $break_end = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->break_end);
                if ($this->timesheetShift->next_day_break) {
                    $break_end->addDay();
                }

                /**
                 * Case 3: Check in between in the rest period.
                 * Return check in which equal: break_start - compromised_minutes.
                 */
                if ($actual_in->isBetween($break_start, $break_end)) {
                    return $break_start->subMinutes($compromised_minutes)->format('Y-m-d H:i:s');
                }

                /**
                 * Case 4: Check in between end_break and (end_break + compromised_minutes).
                 *
                 */
                if (($diff = $actual_in->diffInMinutes($break_end)) < $compromised_minutes) {
                    return $break_start->subMinutes($compromised_minutes - $diff)->format('Y-m-d H:i:s');
                }
            }

            /**
             * Default: Check in equal: check_in - $compromised_minutes.
             */
            return $actual_in->subMinutes($compromised_minutes)->format('Y-m-d H:i:s');

        }

        return $this->check_in;
    }

    public function getConvertedCheckOutAttribute()
    {
        if (!$this->check_out) {
            return $this->check_out;
        }

        $actual_out = Carbon::parse($this->check_out);
        $schedule_out = Carbon::parse($this->timesheet->log_date . ' ' . $this->shift_check_out);

        /**
         * Case 1: Check out after schedule_out or don't meet core_shift condition
         * Return check out.
         */
        if (
            $actual_out->isBefore($schedule_out)
            && $this->applied_shift_type == TimesheetShift::CORE_SHIFT
        ) {
            $core_time_out = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->core_time_out);
            if ($this->timesheetShift->core_next_day) {
                $core_time_out->addDay();
            }

            $compromised_minutes = $schedule_out->diffInMinutes($core_time_out);

            /**
             * Case 2: Check out after core-time
             * Return check out which equal core-time.
             */
            if ($actual_out->greaterThanOrEqualTo($core_time_out)) {
                return $schedule_out->format('Y-m-d H:i:s');
            }

            if ($this->timesheetShift->break_start && $this->timesheetShift->break_end) {
                $break_start = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->break_start);
                if ($this->timesheetShift->next_day_break_start) {
                    $break_start->addDay();
                }

                $break_end = Carbon::parse($this->timesheet->log_date . ' ' . $this->timesheetShift->break_end);
                if ($this->timesheetShift->next_day_break) {
                    $break_end->addDay();
                }

                /**
                 * Case 3: Check out between in the rest period.
                 * Return check out which equal: break_end + compromised_minutes.
                 */
                if ($actual_out->isBetween($break_start, $break_end)) {
                    return $break_end->addMinutes($compromised_minutes)->format('Y-m-d H:i:s');
                }

                /**
                 * Case 4: Check out between (start_break - compromised_minutes) and start_break.
                 *
                 */
                if (($diff = $break_start->diffInMinutes($actual_out)) < $compromised_minutes) {
                    return $break_end->addMinutes($compromised_minutes - $diff)->format('Y-m-d H:i:s');
                }
            }

            /**
             * Default: Check in equal: check_in - $compromised_minutes.
             */
            return $actual_out->addMinutes($compromised_minutes)->format('Y-m-d H:i:s');

        }

        return $this->check_out;
    }

    public function getRestShiftPeriodAttribute()
    {
        $shift = $this->timesheetShift;
        if (!$shift->break_start || !$shift->break_end) {
            return Period::make(
                '2020-01-01 00:00:00',
                '2020-01-01 00:00:01',
                $this->PrecisionType,
                $this->BoundariesType
            );
        }
        $breakIn = Carbon::parse($this->timesheet->log_date . ' ' . $shift->break_start);
        if ($shift->next_day_break_start) {
            $breakIn = $breakIn->addDay();
        }

        $breakOut = Carbon::parse($this->timesheet->log_date . ' ' . $shift->break_end);
        if ($shift->next_day_break) {
            $breakOut = $breakOut->addDay();
        }
        return Period::make($breakIn, $breakOut, $this->PrecisionType, $this->BoundariesType);
    }

    public function getScheduleShiftWithoutRestAttribute()
    {
        return PeriodHelper::subtract($this->schedule_shift_period, $this->rest_shift_period);
    }

    public function getScheduleShiftPeriodAttribute()
    {
        $checkIn = Carbon::parse($this->timesheet->log_date . ' ' . $this->shift_check_in);
        $checkOut = Carbon::parse($this->timesheet->log_date . ' ' . $this->shift_check_out);
        if ($this->shift_next_day) {
            $checkOut->addDay();
        }
        return Period::make($checkIn, $checkOut, $this->PrecisionType, $this->BoundariesType);
    }

    public function getConvertedInOutPeriodAttribute()
    {
        if (!$this->converted_check_in || !$this->converted_check_out) {
            return Period::make(
                '2020-01-01 00:00:00',
                '2020-01-01 00:00:01',
                $this->PrecisionType,
                $this->BoundariesType
            );
        }
        return Period::make($this->converted_check_in, $this->converted_check_out, $this->PrecisionType, $this->BoundariesType);
    }

    public function getBlockInOutPeriodAttribute()
    {
        if (!$this->converted_check_in || !$this->converted_check_out) {
            return Period::make(
                '2020-01-01 00:00:00',
                '2020-01-01 00:00:01',
                $this->PrecisionType,
                $this->BoundariesType
            );
        }

        if ($this->timesheetMinBlock > 1) {
            $roundMethod = function ($p1, $p2, $compare_value, $is_floor)
            {
                if ($is_floor) {
                    $value = floor(($p1 - $p2) / $compare_value);
                } else {
                    $value = ceil(($p1 - $p2) / $compare_value);
                }

                return $p1 - ($value * $compare_value);
            };

            $actualIn = $this->converted_check_in->getTimestamp();
            $actualOut = $this->converted_check_out->getTimestamp();

            $workScheduleIn = strtotime($this->timesheet->log_date . ' ' . $this->shift_check_in);
            $workScheduleOut = $this->shift_next_day ? strtotime($this->timesheet->log_date . ' ' . $this->shift_check_out . '+1 day') : strtotime($this->timesheet->log_date . ' ' . $this->shift_check_out);

            $checkInWithTimeBlock = $roundMethod($workScheduleIn, $actualIn, $this->timesheetMinBlock * 60, 1);
            $in = \Carbon\Carbon::createFromTimestamp($checkInWithTimeBlock);
            $checkOutWithTimeBlock = $roundMethod($workScheduleOut, $actualOut, $this->timesheetMinBlock * 60, 0);
            $out = \Carbon\Carbon::createFromTimestamp($checkOutWithTimeBlock);
        } else {
            $in = $this->converted_check_in;
            $out = $this->converted_check_out;
        }
        return Period::make($in, $out, $this->PrecisionType, $this->BoundariesType);
    }

    public function getRestShiftHoursAttribute()
    {
        return PeriodHelper::countHours($this->rest_shift_period);
    }

    public function getScheduleShiftHoursAttribute()
    {
        if ($this->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT) {
            return $this->timesheetShift->hours;
        }

        return PeriodHelper::countHours($this->schedule_shift_period) - PeriodHelper::countHours($this->rest_shift_period);
    }

    /**
     * If shift is flexible:
     *   Checkin/checkout in timesheetShift is wrapped.
     *   We will get checkin/checkout schedule by user in/out.
     *   If the shift has core_time_in, shift_check_in cannot greater than core_time_in.
     *   If user check_in in the break period, shift_check_in is equal break_end.
     * NOTE: At this time, we don't allow user who have shift_check_in is next_day.
     */

    public function getShiftCheckInAttribute()
    {
        if ($this->timesheetShift->shift_type != TimesheetShift::FLEXIBLE_SHIFT) {
            return $this->timesheetShift->check_in;
        }

        if (!$this->check_in) {
            return $this->timesheetShift->check_in;
        }

        $log_date = $this->timesheet->log_date;
        $shift_check_in = Carbon::parse($this->check_in);

        if ($core_time_in = $this->timesheetShift->core_time_in) {
            $core_time_in = Carbon::parse($log_date . ' ' . $core_time_in);
            if ($shift_check_in->greaterThanOrEqualTo($core_time_in)) {
                return $this->timesheetShift->core_time_in;
            }
        }

        if ($this->timesheetShift->break_start && $this->timesheetShift->break_end) {
            $break_start = Carbon::parse($log_date . ' ' . $this->timesheetShift->break_start);
            if ($this->timesheetShift->next_day_break_start) {
                $break_start->addDay();
            }

            $break_end = Carbon::parse($log_date . ' ' . $this->timesheetShift->break_end);
            if ($this->timesheetShift->next_day_break) {
                $break_end->addDay();
            }

            if ($shift_check_in->isBetween($break_start, $break_end)) {
                $shift_check_in = $break_end;
            }
        }

        if (!$shift_check_in->isSameDay($log_date)) {
            return "23:59";
        }

        return $shift_check_in->format('H:i');
    }

    /**
     * If shift is flexible:
     *   Checkin/checkout in timesheetShift is wrapped.
     *   We will get checkin/checkout schedule by user in/out.
     */

    public function getShiftCheckOutAttribute()
    {
        if ($this->timesheetShift->shift_type != TimesheetShift::FLEXIBLE_SHIFT) {
            return $this->timesheetShift->check_out;
        }

        $log_date = $this->timesheet->log_date;
        $shift_check_in = Carbon::parse($log_date . ' ' . $this->shift_check_in);
        $shift_check_out = $shift_check_in->clone()->addMinutes($this->schedule_shift_hours * 60);

        if ($this->timesheetShift->break_start) {
            $break_start = Carbon::parse($log_date . ' ' . $this->timesheetShift->break_start);
            if ($this->timesheetShift->next_day_break_start) {
                $break_start->addDay();
            }

            if ($shift_check_in->isBefore($break_start) && $shift_check_out->isAfter($break_start)) {
                return $shift_check_out->addMinutes($this->rest_shift_hours * 60)->format('H:i');
            }
        }

        return $shift_check_out->format('H:i');
    }

    public function getShiftNextDayAttribute()
    {
        if ($this->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT) {

            if (!$this->timesheetShift->next_day) {
                return 0;
            }

            return $this->shift_check_out < $this->shift_check_in;
        }

        return $this->timesheetShift->next_day;
    }

    public function getAppliedShiftTypeAttribute()
    {
        if ($this->timesheetShift->shift_type == TimesheetShift::CORE_SHIFT) {
            if (!$this->check_in || !$this->check_out || !$this->timesheetShift->core_time_in || !$this->timesheetShift->core_time_out) {
                return 0;
            }

            if (empty($this->timesheetShift->expected_core_hours)) {
                return TimesheetShift::CORE_SHIFT;
            }

            $log_date = $this->timesheet->log_date;
            $core_time_in = Carbon::parse($log_date . ' ' . $this->timesheetShift->core_time_in);
            $core_time_out = Carbon::parse($log_date . ' ' . $this->timesheetShift->core_time_out);
            if ($this->timesheetShift->core_next_day) {
                $core_time_out->addDay();
            }

            $corePeriod = Period::make($core_time_in, $core_time_out, $this->PrecisionType, $this->BoundariesType);

            $actualPeriod = Period::make($this->check_in, $this->check_out, $this->PrecisionType, $this->BoundariesType);

            $actualPeriod = $corePeriod->overlapSingle($actualPeriod);

            if ($actualPeriod) {
                $actualPeriod = PeriodHelper::subtract($actualPeriod, $this->rest_shift_period);

                $workingHours = $actualPeriod->reduce(function ($carry, $period) {
                        $carry += PeriodHelper::countMinutes($period);
                        return $carry;
                    }, 0) / 60;

                if ($workingHours >= $this->timesheetShift->expected_core_hours) {
                    return TimesheetShift::CORE_SHIFT;
                }
            }

            return 0;
        }

        return $this->timesheetShift->shift_type;
    }

    public function autoCheckInCheckOutForAdjacentShift($timesheetMappings, $log_date)
    {
        /** When checkout, if employee has any shift which have check_out (A) = current shift check_in (B)
         * We set this shift check_out = current shift check_in = shift schedule */
        $thisShiftIn = Carbon::parse($log_date . ' ' . $this->shift_check_in, Constant::TIMESHEET_TIMEZONE);
        foreach ($timesheetMappings as $item) {
            /** If employee don't check_in the shift (A), we will skip logic */
            if (!$item->check_in) {
                continue;
            }

            $otherShiftOut = Carbon::parse($log_date . ' ' . $item->shift_check_out, Constant::TIMESHEET_TIMEZONE);
            if ($item->shift_next_day) {
                $otherShiftOut->addDay();
            }

            if ($thisShiftIn->eq($otherShiftOut)) {
                $this->check_in = $item->check_out = $otherShiftOut->toDateTimeString();
                $item->save();
            }
        }
    }
}
