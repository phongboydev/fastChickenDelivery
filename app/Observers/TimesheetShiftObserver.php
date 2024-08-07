<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Support\ErrorCode;
use App\Models\Timesheet;
use App\Models\TimesheetShift;
use Illuminate\Support\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class TimesheetShiftObserver
{
    public function creating(TimesheetShift $timesheetShift)
    {
      $this->_validate($timesheetShift);
    }

    public function updating(TimesheetShift $timesheetShift)
    {
      $this->_validate($timesheetShift);
    }

    public function deleting(TimesheetShift $timesheetShift)
    {
      $checkTimesheets = Timesheet::where('timesheet_shift_id', $timesheetShift->id)->exists();
      $errorContent = '';
      
      if ($checkTimesheets) {
        $timesheets = Timesheet::where('timesheet_shift_id', $timesheetShift->id)->with("clientEmployee")->get();
        foreach($timesheets as $key => $timesheet){
          if($key < 5){
            $log_date = Carbon::parse($timesheet->log_date)->format('d/m/Y');
            $errorContent = $errorContent . "<p><strong>[ ".$timesheet->clientEmployee->code ." ]</strong> ".$timesheet->clientEmployee->full_name ." - ". __('model.clients.date') ." ". $log_date . "</p>";
          } else {
            $count = $timesheets->count() - 5;
            $errorContent = $errorContent . "<p>".__('count_shift_other'). " <strong>[". $count . "]</strong> </p>";
            break;
          }
        }
        throw new HumanErrorException( __('error.has_shift_set_up_not_delete') . $errorContent, ErrorCode::ERR0006);
      }
    }

    private function _validate(TimesheetShift $timesheetShift) {
      $startTime = Carbon::parse('2022-01-01 ' . $timesheetShift->check_in);
      $endTime   = Carbon::parse('2022-01-01 ' . $timesheetShift->check_out);

      $breakStartTime = Carbon::parse('2022-01-01 ' . $timesheetShift->break_start);
      $breakEndTime   = Carbon::parse('2022-01-01 ' . $timesheetShift->break_end);

      if($endTime->isBefore($startTime) && !$timesheetShift->next_day) {
        
        throw new HumanErrorException(__('error.invalid_time') . ': ' . $timesheetShift->check_in . '-' . $timesheetShift->check_out);
      }

      if(
        $breakEndTime->isBefore($breakStartTime) &&
        ((!$timesheetShift->next_day_break && !$timesheetShift->next_day_break_start) ||
        ($timesheetShift->next_day_break && $timesheetShift->next_day_break_start))
      ) {
        
        throw new HumanErrorException(__('error.invalid_time') . ': ' . $timesheetShift->break_start . '-' . $timesheetShift->break_end);
      }

      if($timesheetShift->next_day) {
        $endTime->addDays();
      }

      if($timesheetShift->next_day_break_start) {
        $breakStartTime->addDays();
      }
      
      if($timesheetShift->next_day_break) {
        $breakEndTime->addDays();
      }

      $workingPeriod = Period::make($startTime, $endTime, Precision::MINUTE);

      if((!$workingPeriod->contains($breakStartTime) && $workingPeriod->contains($breakEndTime)) ||
        ($workingPeriod->contains($breakStartTime) && !$workingPeriod->contains($breakEndTime))
      ) {
        throw new HumanErrorException(__('error.invalid_time'));
      }

      if ($timesheetShift->break_start && $timesheetShift->break_end) {
        $breakTimePeriod = Period::make($breakStartTime, $breakEndTime, Precision::MINUTE);
        if (!$workingPeriod->overlapsWith($breakTimePeriod)) {
          throw new HumanErrorException(__('model.validate.break_time_overlap'));
        }
      }
    }
}

