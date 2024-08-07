<?php


namespace App\Support;


use App\Models\TimesheetShift;
use Illuminate\Support\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class PeriodHelper
{

    /**
     * @param  \Spatie\Period\Period|null  $period
     *
     * @return float
     */
    public static function countHours(Period $period): float
    {
        return round(self::countMinutes($period) / 60, 2);
    }

    public static function convertMinutestoHours($minutes): float
    {
        return round($minutes / 60, 2, PHP_ROUND_HALF_DOWN);
    }

    /**
     * @param  \Spatie\Period\Period|null  $period
     *
     * @return float
     */
    public static function countMinutes(?Period $period): float
    {
        if (!$period) {
            return 0.0;
        }
        $periodStart = (new Carbon($period->getStart()))->roundMinute();
        $periodEnd = (new Carbon($period->getEnd()))->roundMinute();
        $perioudMinutes = $periodStart->diffInMinutes($periodEnd);
        return $perioudMinutes;
    }

    public static function getHourString(\DateTimeInterface $date)
    {
        if ($date instanceof \Carbon\Carbon) {
            $time = $date;
        } else {
            $time = new Carbon($date);
        }
        return $time->format("H:i");
    }

    /**
     * Calculate gaps and round up
     * @param PeriodCollection $collection
     *
     * @return PeriodCollection
     */
    public static function getGaps(PeriodCollection $collection)
    {
        $gaps = $collection->gaps();
        // cac khoan bi thieu gio lam
        $gaps = $gaps->map(function ($v) {
            $s = new \DateInterval("PT1S");
            $start = $v->getStart()->sub($s);
            $end = $v->getEnd()->add($s);
            return new Period($start, $end, Precision::SECOND);
        });
        return $gaps;
    }

    /**
     * @param  \Spatie\Period\Period  $period
     *
     * @return array
     */
    public static function getRoundedStartEndHourString(Period $period): array
    {
        $periodStart = (new Carbon($period->getStart()))->roundMinute();
        $periodEnd = (new Carbon($period->getEnd()))->roundMinute();
        return [
            'start_date' => $periodStart->format('Y-m-d'),
            'start' => self::getHourString($periodStart),
            'end_date' => $periodEnd->format('Y-m-d'),
            'end' => self::getHourString($periodEnd),
        ];
    }

    /**
     * @param $startTime
     * @param $endTime
     * @param  int  $precision
     *
     * @return \Spatie\Period\Period
     */
    public static function makePeriod($startTime, $endTime, int $precision = Precision::SECOND): Period
    {
        $start = !($startTime instanceof Carbon) ? Carbon::parse($startTime) : $startTime;
        $end = !($endTime instanceof Carbon) ? Carbon::parse($endTime) : $endTime;
        if ($start->isAfter($end)) {
            // swap
            return Period::make($end, $start, $precision);
        }
        // let it be
        return Period::make($start, $end, $precision);
    }

    /**
     *
     * @param Period $source
     * @param Period ...$targets
     *
     * @return PeriodCollection
     */
    public static function subtract(Period $source, Period ...$targets): PeriodCollection
    {
        if (!$targets) {
            return new PeriodCollection();
        }

        $diffs = [];

        foreach ($targets as $period) {
            $diffs[] = $source->diffSingle($period);
        }

        return (new PeriodCollection($source))->overlap(...$diffs);
    }

    /**
     *
     * @param PeriodCollection $sources
     * @param PeriodCollection $targets
     *
     * @return PeriodCollection
     */
    public static function subtractPeriodCollection(PeriodCollection $sources, PeriodCollection $targets): PeriodCollection
    {
        if ($targets->isEmpty()) {
            return $sources;
        }

        $diffs = [];

        foreach ($targets as $period) {
            $diffs[] = $period;
        }

        $collect = new PeriodCollection();
        foreach ($sources as $source) {
            $collect = self::merge2Collections($collect, self::subtract($source, ...$diffs));
        }
        return $collect;
    }

    public static function contains(Period $source, PeriodCollection $target)
    {
        foreach ($target as $period) {

            if ($period->getIncludedStart()->getTimestamp() < $source->getIncludedStart()->getTimestamp()) {
                return false;
            }

            if ($period->getIncludedEnd()->getTimestamp() > $source->getIncludedEnd()->getTimestamp()) {
                return false;
            }
        }

        return true;
    }

    public static function merge2Collections(PeriodCollection $first, PeriodCollection $second): PeriodCollection
    {
        $collection = new PeriodCollection();
        if ($first->isEmpty() && $second->isEmpty()) {
            return $collection;
        }

        if ($first->isEmpty()) {
            return $second;
        }

        if ($second->isEmpty()) {
            return $first;
        }

        foreach ($second as $period) {
            $first = $first->add($period);
        }

        return $first;
    }

    public static function union(PeriodCollection &$collection)
    {
        $boundaries = $collection->boundaries();

        if (! $boundaries) {
            return new PeriodCollection();
        }

        $gaps = self::subtractPeriodCollection(new PeriodCollection($boundaries), $collection);

        $collection = self::subtractPeriodCollection(new PeriodCollection($boundaries), $gaps);
    }

    public static function isOverlapByStringTime(TimesheetShift ...$timesheetShifts)
    {
        $data_check = [];
        if (!$timesheetShifts || count($timesheetShifts) == 1) {
            return false;
        }

        foreach ($timesheetShifts as $timesheetShift) {
            if ($timesheetShift->next_day) {
                $data_check[] = [
                    'start_time' => $timesheetShift->check_in,
                    'end_time' => "23:59:59"
                ];
                $data_check[] = [
                    'start_time' => "00:00:00",
                    'end_time' => $timesheetShift->check_out
                ];
            } else {
                $data_check[] = [
                    'start_time' => $timesheetShift->check_in,
                    'end_time' => $timesheetShift->check_out
                ];
            }
        }

        for ($i = 0; $i < count($data_check); $i++) {
            $start_key = $data_check[$i]['start_time'];
            $end_key = $data_check[$i]['end_time'];
            for ($j = $i; $j < count($data_check); $j++) {
                if (
                    $start_key < $data_check[$j]['start_time'] && $data_check[$j]['start_time'] < $end_key
                    || $start_key < $data_check[$j]['end_time'] && $data_check[$j]['end_time'] < $end_key
                ) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     *
     * @param string $date
     * @param string $dayBeginMark
     *
     * @return PeriodCollection
     */
    public static function getNightPeriodsForDay(string $date, string $dayBeginMark): PeriodCollection
    {
        $nextDay = \Carbon\Carbon::parse($date)->addDay();
        if ($dayBeginMark > '22:00') {
            $midnightOTStart1 = $date . ' ' . $dayBeginMark . ':00';
            $midnightOTEnd1 = $nextDay->format('Y-m-d') . ' 06:00:00';
            $midnightOTStart2 = $nextDay->format('Y-m-d') . ' 22:00:00';
            $midnightOTEnd2 = $nextDay->format('Y-m-d') . ' ' . $dayBeginMark . ':00';

            $midnightOTPeriod = new PeriodCollection();
            $midnightOTPeriod = $midnightOTPeriod->add(Period::make($midnightOTStart1, $midnightOTEnd1, Precision::SECOND));
            $midnightOTPeriod = $midnightOTPeriod->add(Period::make($midnightOTStart2, $midnightOTEnd2, Precision::SECOND));
        } else if ($dayBeginMark < '06:00') {
            $midnightOTStart1 = $date . ' ' . $dayBeginMark . ':00';
            $midnightOTEnd1 = $date . ' 06:00:00';
            $midnightOTStart2 = $date . ' 22:00:00';
            $midnightOTEnd2 = $nextDay->format('Y-m-d') . ' ' . $dayBeginMark . ':00';

            $midnightOTPeriod = new PeriodCollection();
            $midnightOTPeriod = $midnightOTPeriod->add(Period::make($midnightOTStart1, $midnightOTEnd1, Precision::SECOND));
            $midnightOTPeriod = $midnightOTPeriod->add(Period::make($midnightOTStart2, $midnightOTEnd2, Precision::SECOND));
        } else {
            $midnightOTStart = $date . ' 22:00:00';
            $midnightOTEnd = $nextDay->format('Y-m-d') . ' 06:00:00';
            $midnightOTPeriod = new PeriodCollection(Period::make($midnightOTStart, $midnightOTEnd, Precision::SECOND));
        }

        return $midnightOTPeriod;
    }
}
