<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Models\OvertimeCategory;
use App\Support\ErrorCode;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class OvertimeCategoryObserver
{
    /**
     * Handle the LeaveCategory "created" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     * @throws HumanErrorException
     */
    public function creating(OvertimeCategory $overtimeCategory)
    {
        $this->validateCommon($overtimeCategory);
        // Validate range start_date and end_date in one year
        $overtimePeriod = Period::make(Carbon::parse($overtimeCategory->start_date), Carbon::parse($overtimeCategory->end_date), Precision::SECOND);
        $overtimeCategoryByRangeDate = OvertimeCategory::where('client_id', $overtimeCategory['client_id'])->get();
        foreach ($overtimeCategoryByRangeDate as $item) {
            if($item['year'] == $overtimeCategory['year']) {
                throw new HumanErrorException(__("error.year_aldready_exits"));
            }
            $overtimePeriodItemDb = Period::make(Carbon::parse($item->start_date), Carbon::parse($item->end_date), Precision::SECOND);
            $overlap = $overtimePeriod->overlapSingle($overtimePeriodItemDb);
            if ($overlap) {
                throw new HumanErrorException(__("error.range_date_aldready_exit_in_another"));
            }
        }
    }

    /**
     * Handle the OvertimeCategory "created" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     */
    public function created(OvertimeCategory $overtimeCategory)
    {
        //
    }

    /**
     * Handle the OvertimeCategory "updated" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     * @throws HumanErrorException
     */
    public function updating(OvertimeCategory $overtimeCategory)
    {
        $this->validateCommon($overtimeCategory);
        // Validate range start_date and end_date in one year
        $overtimePeriod = Period::make(Carbon::parse($overtimeCategory->start_date), Carbon::parse($overtimeCategory->end_date), Precision::SECOND);
        $overtimeCategoryByRangeDate = OvertimeCategory::where('client_id', $overtimeCategory['client_id'])->get();
        foreach ($overtimeCategoryByRangeDate as $item) {
            if($item['id'] == $overtimeCategory['id']) {
                continue;
            }
            $overtimePeriodItemDb = Period::make(Carbon::parse($item->start_date), Carbon::parse($item->end_date), Precision::SECOND);
            $overlap = $overtimePeriod->overlapSingle($overtimePeriodItemDb);
            if ($overlap) {
                throw new HumanErrorException(__("error.range_date_aldready_exit_in_another"));
            }
        }
    }

    /**
     * Handle the OvertimeCategory "updated" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     */
    public function updated(OvertimeCategory $overtimeCategory)
    {
        //
    }

    /**
     * Handle the OvertimeCategory "deleted" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     */
    public function deleted(OvertimeCategory $overtimeCategory)
    {
        //
    }

    /**
     * Handle the OvertimeCategory "restored" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     */
    public function restored(OvertimeCategory $overtimeCategory)
    {
        //
    }

    /**
     * Handle the OvertimeCategory "force deleted" event.
     *
     * @param OvertimeCategory $overtimeCategory
     * @return void
     */
    public function forceDeleted(OvertimeCategory $overtimeCategory)
    {
        //
    }

    public function validateCommon(OvertimeCategory $overtimeCategory) {
        // Validate number hours of month and year
        if($overtimeCategory['entitlement_month'] < 0 && $overtimeCategory['entitlement_year'] < 0) {
            throw new HumanErrorException(__("error.hours_must_greater_than_0"), ErrorCode::ERR0004);
        }
        // Validate number hours of month < year
        if($overtimeCategory['entitlement_month'] > $overtimeCategory['entitlement_year']) {
            throw new HumanErrorException(__("error.hours_month_must_less_than_hours_year"), ErrorCode::ERR0004);
        }
    }
}
