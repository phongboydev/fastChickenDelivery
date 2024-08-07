<?php

namespace App\Observers;

use App\Models\ApproveFlow;
use App\Models\CalculationSheetClientEmployee;
use App\Models\ClientEmployeePayslipComplaint;
use App\Notifications\PayslipComplaintNotification;
use App\User;
use Illuminate\Support\Facades\App;

class ClientEmployeePayslipComplaintObserver
{
    /**
     * Handle the ClientEmployeePayslipComplaint "created" event.
     *
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return void
     */
    public function created(ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        // Notify all people authorized to manage payroll
        $approval_flow = ApproveFlow::where(['flow_name' => 'manage-payroll', 'client_id' => auth()->user()->client_id])->with([
            'approveFlowUsers'
        ])->first();

        if (count($approval_flow->approveFlowUsers) > 0) {
            foreach ($approval_flow->approveFlowUsers as $approveFlowUsers) {
                $user = User::find($approveFlowUsers->user_id);
                $language = $user->prefered_language ? $user->prefered_language : 'en';
                App::setLocale($language);

                $data = [
                    'payslip_complaint' => $clientEmployeePayslipComplaint,
                    'send_to_user' => false,
                    'full_name' => $approveFlowUsers->user->clientEmployee->full_name
                ];

                $user->notify((new PayslipComplaintNotification($data))->delay(now()->addSecond(15)));
            }
        }
    }

    /**
     * Handle the ClientEmployeePayslipComplaint "updated" event.
     *
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return void
     */
    public function updated(ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        $csce = CalculationSheetClientEmployee::find($clientEmployeePayslipComplaint->calculation_sheet_client_employee_id);

        if ($clientEmployeePayslipComplaint->isDirty('state')) {
            // Notify to user
            $user = User::find($csce->clientEmployee->user_id);
            $language = $user->prefered_language ? $user->prefered_language : 'en';
            App::setLocale($language);

            $data = [
                'payslip_complaint' => $clientEmployeePayslipComplaint,
                'send_to_user' => true
            ];

            $user->notify((new PayslipComplaintNotification($data))->delay(now()->addSecond(15)));
        }
    }

    /**
     * Handle the ClientEmployeePayslipComplaint "deleted" event.
     *
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return void
     */
    public function deleted(ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }

    /**
     * Handle the ClientEmployeePayslipComplaint "restored" event.
     *
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return void
     */
    public function restored(ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }

    /**
     * Handle the ClientEmployeePayslipComplaint "force deleted" event.
     *
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return void
     */
    public function forceDeleted(ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }
}
