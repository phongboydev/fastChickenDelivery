<?php

namespace App\Observers;

use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetTemplate;
use App\User;
use App\Models\IglocalEmployee;
use App\Notifications\CalculationSheetTemplateNotification;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
class CalculationSheetTemplateObserver
{

    public function creating(CalculationSheetTemplate $calculationSheet)
    {
        $calculationSheet->is_enabled = false;
    }

    /**
     * Handle the calculation sheet "created" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function created(CalculationSheetTemplate $calculationSheetTemplate)
    {
        $user = Auth::user();

        $role = $user->getRole();

        if ( $user->isInternalUser() && ($role == Constant::ROLE_INTERNAL_STAFF) ) {

            $assignmentUsers = User::systemNotifiable()->with('iGlocalEmployee')->with('client')->get();
            $assignmentUsers->each(function(User $assignmentUser) use ($calculationSheetTemplate, $user) {

                $role = isset($assignmentUser->iGlocalEmployee['role']) ? $assignmentUser->iGlocalEmployee['role'] : false;
                $creator = $user->iGlocalEmployee;

                switch ($role) {
                    case Constant::ROLE_INTERNAL_LEADER:
                        if ($assignmentUser->iGlocalEmployee->isAssignedFor($calculationSheetTemplate->client_id)) {
                            $assignmentUser->notify(new CalculationSheetTemplateNotification($assignmentUser, $creator, $calculationSheetTemplate));
                        }
                    default:
                        logger()->warning("The user of iGlocal is don't role");
                }
            });
        }
    }

    /**
     * Handle the calculation sheet "updated" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function updated(CalculationSheetTemplate $calculationSheet)
    {

    }

    /**
     * Handle the calculation sheet "deleted" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function deleted(CalculationSheetTemplate $calculationSheet)
    {
        CalculationSheetClientEmployee::where('calculation_sheet_id', $calculationSheet->id)->delete();
    }

    /**
     * Handle the calculation sheet "restored" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function restored(CalculationSheetTemplate $calculationSheet)
    {
        //
    }

    /**
     * Handle the calculation sheet "force deleted" event.
     *
     * @param  \App\CalculationSheet  $calculationSheet
     * @return void
     */
    public function forceDeleted(CalculationSheetTemplate $calculationSheet)
    {
    }

}
