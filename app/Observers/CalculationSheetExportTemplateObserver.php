<?php

namespace App\Observers;

use App\Models\CalculationSheetExportTemplate;
use App\User;
use App\Models\IglocalEmployee;
use App\Notifications\CalculationSheetTemplateNotification;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
class CalculationSheetExportTemplateObserver
{

    public function creating(CalculationSheetExportTemplate $calculationSheet)
    {
    }

    /**
     * Handle the calculation sheet "created" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function created(CalculationSheetExportTemplate $calculationSheetTemplate)
    {

    }

    /**
     * Handle the calculation sheet "updated" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function updated(CalculationSheetExportTemplate $template)
    {
        $media = $template->getMediaModel();

        if(!empty($media)){
            CalculationSheetExportTemplate::where('id', $template->id)->update([
                'file_name' => $media[0]->getPath()
            ]);

        }
    }

    /**
     * Handle the calculation sheet "deleted" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function deleted(CalculationSheetExportTemplate $calculationSheet)
    {
    }

    /**
     * Handle the calculation sheet "restored" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function restored(CalculationSheetExportTemplate $calculationSheet)
    {
        //
    }

    /**
     * Handle the calculation sheet "force deleted" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function forceDeleted(CalculationSheetExportTemplate $calculationSheet)
    {
    }

}
