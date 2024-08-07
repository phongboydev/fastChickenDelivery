<?php

namespace App\Observers;

use App\Support\ClientHelper;
use App\Support\Constant;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeGroupAssignment;
use App\Models\ClientEmployeeSalaryHistory;
use App\Models\CalculationSheetTemplateAssignment;
use App\Models\ClientAssignment;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\CalculationSheet;
use App\Models\Approve;
use App\User;
use Carbon\Carbon;
use App\Notifications\ClientEmployeeUpdateNotification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendActivationUserEmail;
use App\Jobs\SendCreatedEmployeeEmail;
use App\Jobs\UpdateClientDepartment;
use App\Exceptions\HumanErrorException;
use App\Support\FormatHelper;
use App\Support\LeaveHelper;

class ClientEmployeeObserver
{

    public function creating(ClientEmployee $clientEmployee)
    {
        $client = $clientEmployee->client;
        $defaultTemplate = $client->workScheduleGroupTemplates->where('is_default', true)->first();
        $clientEmployee->work_schedule_group_template_id = $defaultTemplate ? $defaultTemplate->id : null;
        $clientEmployee->year_paid_leave_count ??= 0;
        $clientEmployee->sex = FormatHelper::gender($clientEmployee->sex);
        // $clientEmployee->status = empty($clientEmployee->status) ?? Constant::CLIENT_EMPLOYEE_STATUS_WORKING;
    }

    public function created(ClientEmployee $clientEmployee)
    {
        if (!empty($clientEmployee->user->email)) {
            SendCreatedEmployeeEmail::dispatch($clientEmployee);
        }

        $this->updateClientDepartment($clientEmployee->client_id);

        // Generate leave balance
        ClientEmployee::withoutEvents(function () use ($clientEmployee) {
            ClientEmployee::where('id', $clientEmployee->id)->update([
                'leave_balance' => json_encode(LeaveHelper::LEAVE_BALANCES)
            ]);
        });
    }

    public function updating(ClientEmployee $clientEmployee)
    {
        $currentClientEmployee = ClientEmployee::where('id', $clientEmployee->id)->first();

        $clientEmployee->sex = FormatHelper::gender($clientEmployee->sex);

        if (!empty($currentClientEmployee)) {

            $isLikeSalaryBefore     = $currentClientEmployee->salary == $clientEmployee->salary;
            $isLikeAResBefore       = $currentClientEmployee->allowance_for_responsibilities == $clientEmployee->allowance_for_responsibilities;
            $isLikeFixBefore       = $currentClientEmployee->fixed_allowance == $clientEmployee->fixed_allowance;
            $isLikeStatusBefore     = $currentClientEmployee->status == $clientEmployee->status;

            if (
                !$isLikeStatusBefore &&
                $currentClientEmployee->status == 'nghỉ việc' &&
                !ClientHelper::validateLimitActivatedEmployee($currentClientEmployee->client_id)
            ) {
                throw new HumanErrorException(__('error.exceeded_employee_limit'));
            }

            if (!$isLikeSalaryBefore || !$isLikeAResBefore || !$isLikeFixBefore) {

                $oldClientEmployee      = ClientEmployee::select('salary', 'allowance_for_responsibilities', 'fixed_allowance')->where('id', $clientEmployee->id)->first();

                $data = [
                    'client_employee_id' => $clientEmployee->id,
                    'old_salary' => $oldClientEmployee->salary,
                    'new_salary' => $clientEmployee->salary,
                    'start_date' => Carbon::now()
                ];

                if ($clientEmployee->allowance_for_responsibilities) {
                    $data['old_allowance_for_responsibilities'] = $oldClientEmployee->allowance_for_responsibilities;
                    $data['new_allowance_for_responsibilities'] = $clientEmployee->allowance_for_responsibilities;
                }

                if ($clientEmployee->fixed_allowance) {
                    $data['old_fixed_allowance'] = $oldClientEmployee->fixed_allowance;
                    $data['new_fixed_allowance'] = $clientEmployee->fixed_allowance;
                }

                // Check salary history
                $salary_history = ClientEmployeeSalaryHistory::where('client_employee_id', $clientEmployee->id)->latest()->first();
                if ($salary_history) {
                    $salary_history->fill(['end_date' => Carbon::now()]);
                    $salary_history->save();
                }

                ClientEmployeeSalaryHistory::create($data);
            }

            if (!empty($clientEmployee->user_id)) {
                $user = User::find($clientEmployee->user_id);
                if (!empty($user)) {
                    $user->name = $clientEmployee->full_name;
                    $user->code = $clientEmployee->code;
                    $user->is_active = 1;
                    $user->update();
                } else {
                    logger()->warning("User is not existed, but linked to ClientEmployee", [
                        "userId" => $clientEmployee->user_id,
                        "clientEmployeeId" => $clientEmployee->id
                    ]);
                }
            } else {

                if ($currentClientEmployee->user_id) {
                    User::where('id', $currentClientEmployee->user_id)->update(['is_active' => 0]);

                    $clientEmployee->user_id = $currentClientEmployee->user_id;
                }
            }

            if (!$isLikeStatusBefore && $clientEmployee->status == 'nghỉ việc') {
                CalculationSheetTemplateAssignment::where('client_employee_id', $clientEmployee->id)->delete();
                ClientAssignment::where('staff_id', $clientEmployee->id)->orWhere('leader_id', $clientEmployee->id)->delete();
                ApproveFlowUser::where('user_id', $clientEmployee->user_id)->delete();
                $employee = ClientEmployee::where('id', $clientEmployee->id)->first();
                if (!empty($employee)) {
                    $employee->work_schedule_group_template_id = null;
                    $employee->update();
                }
                $user = User::find($clientEmployee->user_id);
                if (!empty($user)) {
                    $user->is_active = 0;
                    $user->update();
                }
            }

            if (!$currentClientEmployee->user_id && $clientEmployee->user_id) {
                $this->notifyNewUser($clientEmployee);
            }
        }
    }

    protected function notifyNewUser($clientEmployee)
    {
        // User created
        $random_password = Str::random(10);
        $user = User::find($clientEmployee->user_id);
        $user->password = Hash::make($random_password);
        $user->update();

        SendActivationUserEmail::dispatch(
            $clientEmployee->client,
            $user,
            $clientEmployee,
            $random_password
        );
    }

    public function updated(ClientEmployee $clientEmployee)
    {

        /** @var User $user */
        $user = Auth::user();

        if ($user && !$user->isInternalUser()) {
            $action = 'update';
            $assignmentUsers = User::systemNotifiable()->with('iGlocalEmployee')->get();
            $assignmentUsers->each(function (User $assignmentUser) use ($clientEmployee, $user, $action) {
                $role = isset($assignmentUser->iGlocalEmployee['role']) ? $assignmentUser->iGlocalEmployee['role'] : false;
                logger()->info($role);
                switch ($role) {
                    case Constant::ROLE_INTERNAL_STAFF:
                    case Constant::ROLE_INTERNAL_LEADER:
                        if ($assignmentUser->iGlocalEmployee->isAssignedFor($clientEmployee->client_id)) {

                            try {
                                $assignmentUser->notify(new ClientEmployeeUpdateNotification($user, $clientEmployee, $action));
                            } catch (\Exception $e) {
                                logger()->warning("ClientEmployeeUpdateNotification can not sent email");
                            }
                        }
                        logger()->warning("The user of iGlocal is don't role");
                    default:
                        logger()->warning("The user of iGlocal is don't role");
                }
            });
        } elseif ($user && $user->isInternalUser()) {

            $currentClientEmployee = ClientEmployee::where('id', $clientEmployee->id)->first();

            if (!$currentClientEmployee->user_id && $clientEmployee->user_id) {
                $this->notifyNewUser($clientEmployee);
            }
        }


        $this->updateClientDepartment($clientEmployee->client_id);
    }

    public function deleting(ClientEmployee $clientEmployee)
    {
        $this->updateApprovePayroll($clientEmployee);
    }

    public function deleted(ClientEmployee $clientEmployee)
    {
        CalculationSheetTemplateAssignment::where('client_employee_id', $clientEmployee->id)->delete();
        ClientAssignment::where('staff_id', $clientEmployee->id)->orWhere('leader_id', $clientEmployee->id)->delete();
        ClientEmployeeGroupAssignment::where('client_employee_id', $clientEmployee->id)->delete();

        $this->updateClientDepartment($clientEmployee->client_id);

        User::where('id', $clientEmployee->user_id)->delete();
    }

    protected function updateApprovePayroll(ClientEmployee $clientEmployee)
    {
        if (!$clientEmployee->user_id) return;

        $approveUserFlow = ApproveFlowUser::whereHas('approveFlow', function ($query) {
            $query->where('flow_name', 'CLIENT_REQUEST_PAYROLL')->where('group_id', '0');
        })->where('user_id', $clientEmployee->user_id)->first();

        logger('updateApprovePayroll_2', [$approveUserFlow]);

        if (!empty($approveUserFlow)) {

            $approves = Approve::where('type', 'CLIENT_REQUEST_PAYROLL')
                ->where('assignee_id', $clientEmployee->user_id)
                ->where('client_employee_group_id', '0')
                ->whereNull('approved_at')->get();

            logger('updateApprovePayroll_3', [$approves]);

            if ($approves->isNotEmpty()) {

                Approve::whereIn('id', $approves->pluck('id')->all())->delete();

                $targetIds = $approves->pluck('target_id')->all();

                logger('updateApprovePayroll_4', [$targetIds]);

                CalculationSheet::whereIn('id', $targetIds)->update(['status' => 'director_review']);

                $approveFlow = ApproveFlow::where('flow_name', 'INTERNAL_MANAGE_CALCULATION')->where('group_id', '0')->orderBy('step', 'desc')->first();

                logger('updateApprovePayroll_5', [$approveFlow]);

                if (!empty($approveFlow)) {
                    Approve::whereIn('target_id', $targetIds)
                        ->where('type', 'INTERNAL_MANAGE_CALCULATION')
                        ->where('step', $approveFlow->step)
                        ->where('client_employee_group_id', '0')
                        ->update(['approved_at' => NULL]);
                }
            }
        }

        ApproveFlowUser::where('user_id', $clientEmployee->user_id)->delete();
    }

    protected function updateClientDepartment($clientId)
    {
        UpdateClientDepartment::dispatch(['client_id' => $clientId]);
    }
}
