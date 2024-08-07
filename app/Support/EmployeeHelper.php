<?php


namespace App\Support;


use Illuminate\Support\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class PeriodHelper
{
    public static function checkLastYearPaidLeaveExpiry($lastYearPaidLeaveExpiry, $entitlementLastYear, $workTimeRegisterPeriods)
    {
        if (!is_null($lastYearPaidLeaveExpiry) && Carbon::now()->lte($lastYearPaidLeaveExpiry) && $entitlementLastYear > 0) {
            $periods = new PeriodCollection();
            foreach ($workTimeRegisterPeriods as $period) {
                $date = Carbon::parse($period->date_time_register)->format('Y-m-d');
                $startTime = date('Y-m-d H:i:s', strtotime($date . ' ' . $period->start_time));
                $endTime = date('Y-m-d H:i:s', strtotime(isset($period->next_day) && $period->next_day == 1 ? Carbon::parse($date . ' ' . $period->end_time)->addDay()->format('Y-m-d H:i') : $date . ' ' . $period->end_time));
                $periods->push(Period::make($startTime, $endTime, Precision::SECOND));
            }
            return $periods;
        }
        return null;
    }
}
