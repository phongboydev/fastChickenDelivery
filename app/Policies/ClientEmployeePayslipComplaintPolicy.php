<?php

namespace App\Policies;

use App\Models\ClientEmployeePayslipComplaint;
use App\Models\ClientWorkflowSetting;
use App\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientEmployeePayslipComplaintPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        $clientWorkflowSetting =  ClientWorkflowSetting::where('client_id', $user->client_id)->first(['advanced_approval_flow']);

        if ($clientWorkflowSetting && $clientWorkflowSetting->advanced_approval_flow) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint, array $injected)
    {
        $payslip_complaint_deadline = $clientEmployeePayslipComplaint->calculationSheet->payslip_complaint_deadline;

        if (!isset($injected['state']) && $clientEmployeePayslipComplaint->clientEmployee->id == auth()->user()->clientEmployee->id && $payslip_complaint_deadline) {
            $today = Carbon::today();
            $payslip_complaint_deadline = Carbon::parse($payslip_complaint_deadline)->format('Y-m-d H:i:s');

            if ($clientEmployeePayslipComplaint->state == 'new' && $today->lte($payslip_complaint_deadline)) {
                return true;
            }
            return false;
        } else {
            if ($user->hasAnyPermission(['manage-payroll-complaint'])) {
                return true;
            }
            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        $payslip_complaint_deadline = $clientEmployeePayslipComplaint->calculationSheet->payslip_complaint_deadline;

        if ($clientEmployeePayslipComplaint->clientEmployee->id == auth()->user()->clientEmployee->id) {
            $today = Carbon::today();
            $payslip_complaint_deadline = Carbon::parse($payslip_complaint_deadline)->format('Y-m-d H:i:s');

            if ($clientEmployeePayslipComplaint->state == 'new' && $today->lte($payslip_complaint_deadline)) {
                return true;
            }
            return false;
        } else {
            if ($user->hasAnyPermission(['manage-payroll-complaint'])) {
                return true;
            }
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\ClientEmployeePayslipComplaint  $clientEmployeePayslipComplaint
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, ClientEmployeePayslipComplaint $clientEmployeePayslipComplaint)
    {
        //
    }
}
