<?php

namespace App\Observers;

use App\Models\HeadcountPeriodSetting;

class HeadcountPeriodSettingObserver
{
    /**
     * Handle the HeadcountPeriodSetting "created" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function created(HeadcountPeriodSetting $headcountPeriodSetting)
    {

    }

    /**
     * Handle the HeadcountPeriodSetting "updating" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function updating(HeadcountPeriodSetting $headcountPeriodSetting)
    {
        // To increase performance, with case update:
        // we will delete all cost setting and add all new records
        // instead of upsert
        $headcountPeriodSetting->forceDeleteHeadcountCostSettingById();
    }

    /**
     * Handle the HeadcountPeriodSetting "updated" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function updated(HeadcountPeriodSetting $headcountPeriodSetting)
    {
        //
    }

    /**
     * Handle the HeadcountPeriodSetting "deleted" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function deleted(HeadcountPeriodSetting $headcountPeriodSetting)
    {
        // delete cost settings which relate to period setting.
        $headcountPeriodSetting->deleteHeadcountCostSettingById();
    }

    /**
     * Handle the HeadcountPeriodSetting "restored" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function restored(HeadcountPeriodSetting $headcountPeriodSetting)
    {
        //
    }

    /**
     * Handle the HeadcountPeriodSetting "force deleted" event.
     *
     * @param  \App\Models\HeadcountPeriodSetting  $headcountPeriodSetting
     * @return void
     */
    public function forceDeleted(HeadcountPeriodSetting $headcountPeriodSetting)
    {
        //
    }
}
