<?php

namespace App\Models;

use App\Support\PeriodHelper;
use App\Support\WorktimeRegisterHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Period\Period;

/**
 * @property-read float schedule_work_hours
 * @property-read Period rest_period
 */
class ViewCombinedTimesheet extends Model
{
    // this is a View, NOT table
    protected $table = "view_combined_timesheets";

    public function timesheetShiftMapping()
    {
        return $this->hasMany(TimesheetShiftMapping::class, 'timesheet_id', 'timesheet_id');
    }

    public function getRestPeriodAttribute(): Period
    {
        try {
            if($this->shift_enabled){

                if(empty($this->shift_break_start)) {
                    $this->shift_break_start = '00:00';
                }

                if(empty($this->shift_break_end)){
                    $this->shift_break_end = '00:00';
                }

                $breakIn = Carbon::parse('2021-01-01 ' . $this->shift_break_start . ':00');
                if ($this->shift_break_start < $this->shift_check_in && $this->shift_next_day_break) {
                    $breakIn = $breakIn->addDay();
                }
                $breakOut = Carbon::parse('2021-01-01 ' . $this->shift_break_end . ':00');
                if ($this->shift_break_end < $this->shift_check_out && $this->shift_next_day_break) {
                    $breakOut = $breakOut->addDay();
                }

            } else {

                if(empty($this->schedule_start_break)) {
                    $this->schedule_start_break = '00:00';
                }

                if(empty($this->schedule_end_break)){
                    $this->schedule_end_break = '00:00';
                }

                $breakIn = Carbon::parse('2021-01-01 ' . $this->schedule_start_break . ':00');
                $breakOut = Carbon::parse('2021-01-01 ' . $this->schedule_end_break . ':00');
            }

            return PeriodHelper::makePeriod($breakIn, $breakOut);
        } catch (\Exception $e) {
            logger()->debug('WorkSchedule@getRestPeriodAttribute invalid break time', [
                'start' => $this->start_break,
                'end' => $this->end_break,
            ]);
        }
        return PeriodHelper::makePeriod('2021-01-01 00:00:00', '2021-01-01 00:00:00');
    }

    public function getScheduleWorkHoursAttribute()
    {
        if($this->shift_enabled){
            if($this->shift_is_off_day || $this->shift_is_holiday){
                return 0;
            }

            if(empty($this->shift_check_in)) {
                $this->shift_check_in = '00:00';
            }

            if(empty($this->shift_check_out)){
                $this->shift_check_out = '00:00';
            }

            $checkIn = Carbon::parse('2021-01-01 ' . $this->shift_check_in . ':00');
            $checkOut = Carbon::parse('2021-01-01 ' . $this->shift_check_out . ':00');
            //
            if($this->shift_next_day){
                $checkOut = $checkOut->addDay();
            }
        }else{
            if ($this->is_holiday || $this->is_off_day) {
                return 0;
            }

            if(empty($this->schedule_check_in)) {
                $this->schedule_check_in = '00:00';
            }

            if(empty($this->schedule_check_out)){
                $this->schedule_check_out = '00:00';
            }

            $checkIn = Carbon::parse('2021-01-01 ' . $this->schedule_check_in . ':00');
            $checkOut = Carbon::parse('2021-01-01 ' . $this->schedule_check_out . ':00');
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
    }

    public function getCheckinStringAttribute(): string
    {
        if ($this->check_in) {
            return $this->check_in;
        }

        return "";
    }

    public function getCheckoutStringAttribute(): string
    {
        if ($this->check_out) {
            return $this->check_out;
        }

        return "";
    }

    public function getIsHolidayAttribute(): string
    {
        if ($this->shift_enabled) {
            if($this->attributes['is_holiday']) {
                return $this->attributes['is_holiday'];
            }
            return $this->attributes['shift_is_holiday'];
        }
        return $this->attributes['is_holiday'];
    }
    public function setIsHolidayAttribute($value)
    {
        $clientEmployee = ClientEmployee::find($this->client_employee_id);
        if ($clientEmployee->date_of_entry && $clientEmployee->date_of_entry != '0000-00-00') {
            $this->attributes['is_holiday'] = $this->log_date >= $clientEmployee->date_of_entry;
        } else {
            $this->attributes['is_holiday'] = $value;
        }
        if($value) {
            $this->attributes['shift_is_off_day'] = false;
            $this->attributes['is_off_day'] = false;
        }
    }
    public function getIsOffDayAttribute(): string
    {
        if ($this->shift_enabled) {
            return $this->attributes['shift_is_off_day'];
        }
        return $this->attributes['is_off_day'];
    }

    public function getScheduleCheckinStringAttribute()
    {
        if ($this->shift_enabled) {
            return $this->shift_check_in;
        }

        return $this->schedule_check_in;
    }

    public function getCheckinLateAndCheckOutEarlyLeave($workTimeRegisterPeriod = null)
    {
        $checkInLate = false;
        $checkOutEarly = false;
        $startBreakTime = null;
        $endBreakTime = null;
        if (!empty($ws->start_break) && !empty($ws->end_break)) {
            $startBreakTime = Carbon::parse($this->log_date . " " . $ws->schedule_start_break . ":00");
            $endBreakTime = Carbon::parse($this->log_date . " " . $ws->schedule_end_break . ":00");
        }
        if (!$this->is_holiday && !$this->is_off_day) {
            if (!empty($this->check_in) &&
                $this->check_in != '00:00' && $this->schedule_checkin_string) {
                // Work schedule
                $checkInWork = Carbon::parse($this->log_date . ' ' . $this->schedule_checkin_string . ':00');
                // Check in
                $checkIn = Carbon::parse($this->log_date . ' ' . $this->check_in . ':00');

                if ($workTimeRegisterPeriod) {
                    $firstPeriod = $workTimeRegisterPeriod->first();
                    $param = [
                        'mode' => 'check_in',
                        'work_check_in' => $checkInWork,
                        'start_break_time' => $startBreakTime,
                        'end_break_time' => $endBreakTime,
                        'employee_check_in' => $checkIn,
                        'start_period' => Carbon::parse($firstPeriod->date_time_register . " " . $firstPeriod->start_time),
                        'end_period' => Carbon::parse($firstPeriod->date_time_register . " " . $firstPeriod->end_time),
                    ];
                    $checkInLate = (bool)WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                } else if ($checkIn->isAfter($checkInWork)) {
                    $checkInLate = true;
                }
            }
            if (!empty($this->check_out) && $this->check_out != '00:00' && $this->schedule_checkout_string) {
                if ($this->next_day) {
                    $checkOutWork = Carbon::parse($this->log_date . ' ' . $this->schedule_checkout_string . ':00')->addDay();
                } else {
                    $checkOutWork = Carbon::parse($this->log_date . ' ' . $this->schedule_checkout_string . ':00');
                }
                $checkOut = Carbon::parse($this->log_date . ' ' . $this->check_out . ':00');
                // Check out
                if ($this->next_day) {
                    $checkOut = $checkOut->addDay();
                }
                if ($workTimeRegisterPeriod) {
                    $lastPeriod = $workTimeRegisterPeriod->last();
                    $param = [
                        'mode' => 'check_out',
                        'work_check_out' => $checkOutWork,
                        'start_break_time' => $startBreakTime,
                        'end_break_time' => $endBreakTime,
                        'employee_check_out' => $checkOut,
                        'start_period' => Carbon::parse($lastPeriod->date_time_register . " " . $lastPeriod->start_time),
                        'end_period' => Carbon::parse($lastPeriod->date_time_register . " " . $lastPeriod->end_time),
                    ];
                    $checkOutEarly = (bool)WorktimeRegisterHelper::isCheckInLateOrCheckOutEarly($param);
                } else if ($checkOut->isBefore($checkOutWork)) {
                    $checkOutEarly = true;
                }
            }
        }

        return [$checkInLate, $checkOutEarly];
    }

    public function getScheduleCheckoutStringAttribute()
    {
        return $this->shift_enabled ? $this->shift_check_out : $this->schedule_check_out;
    }

    public function getNextDayAttribute()
    {
        if($this->shift_enabled && $this->shift_next_day) {
            $this->next_day = true;
        }
        return $this->attributes['next_day'];
    }

    public function leavePeriodRequests()
    {
        return $this->hasMany(WorktimeRegister::class, 'client_employee_id', 'client_employee_id')
            ->where('start_time', '<=', $this->schedule_date . ' 23:59:59')
            ->where('end_time', '>=', $this->schedule_date)
            ->where('type', 'leave_request')
            ->whereIn('status', ['approved', 'pending'])
            ->with('periods', function ($q) {
                $q->where('date_time_register', $this->schedule_date)
                    ->where('type_register', 1);
            })
            ->withCount(['periods' => function ($q) {
                $q->where('date_time_register', $this->schedule_date)
                    ->where('type_register', 1);
            }]);
    }

    public function periodRequests()
    {
        return $this->hasMany(WorktimeRegister::class, 'client_employee_id', 'client_employee_id')
            ->where('start_time', '<=', $this->schedule_date . ' 23:59:59')
            ->where('end_time', '>=', $this->schedule_date)
            // ->where('type', 'leave_request')
            ->whereIn('status', ['approved', 'pending'])
            ->with('periods', function ($q) {
                $q->where('date_time_register', $this->schedule_date)
                    ->where('type_register', 1);
            })
            ->withCount(['periods' => function ($q) {
                $q->where('date_time_register', $this->schedule_date)
                    ->where('type_register', 1);
            }]);
    }

    public function getLeavePeriodRequests()
    {
        return $this->leavePeriodsRequests()->get();
    }

    public function getStateLabelAttribute()
    {
        switch ($this->state) {
            case 'new':
                return trans('model.procedure.status.new');
            case 'rejected':
                return trans('model.clients.rejected');
            case 'processing':
                return trans('model.timesheets.state.processing');
            case 'approved':
                return trans('model.clients.approved');
            default:
                return "";
        }
        return "";
    }
}
