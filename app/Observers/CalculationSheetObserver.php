<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Jobs\PrepareSystemCalculationSheetVariablesJob;
use App\Jobs\PrepareUserCalculationSheetVariablesJob;
use App\Jobs\ProcessScheduleMail;
use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\ApproveGroup;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetTemplateAssignment;
use App\Models\CalculationSheetVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeSalary;
use App\Models\ClientPayrollHeadCount;
use App\Models\ClientWorkflowSetting;
use App\Models\WorkTimeRegisterTimesheet;
use App\Notifications\ClientEmployeePayslipNotification;
use App\Support\ApproveObserverTrait;
use App\Support\ClientHelper;
use App\Support\Constant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\CustomException;

class CalculationSheetObserver
{

    use ApproveObserverTrait;

    public function creating(CalculationSheet $calculationSheet)
    {
    }

    /**
     * Handle the calculation sheet "created" event.
     *
     * @param CalculationSheet $calculationSheet
     *
     * @return void
     */
    public function created(CalculationSheet $calculationSheet)
    {
        $assignments = CalculationSheetTemplateAssignment::query()
            ->where('template_id', $calculationSheet->calculation_sheet_template_id)
            ->get();
        $calculationSheetEmployees = $assignments->pluck('client_employee_id')
            ->map(function ($employeeId) use ($calculationSheet) {
                return [
                    // TODO should not do this here
                    'calculation_sheet_id' => $calculationSheet->id,
                    'client_employee_id' => $employeeId,
                    'calculated_value' => 0,
                    // fill be filled by client
                ];
            });

        // Jobs to be done after creating
        $jobs = collect();
        foreach ($calculationSheetEmployees as $calculationSheetEmployee) {
            $model = new CalculationSheetClientEmployee($calculationSheetEmployee);
            $model->save();
            $jobs->push(
                new PrepareSystemCalculationSheetVariablesJob($calculationSheet, $model)
            );
        }
        $jobs->push(
            new PrepareUserCalculationSheetVariablesJob($calculationSheet)
        );

        // Dispatch jobs
        $jobs->each(function ($job) {
            dispatch($job);
        });
        if (!$calculationSheet->is_internal) {
            $approveFlow = ApproveFlow::where('flow_name', 'CLIENT_REQUEST_PAYROLL')->where('client_id', $calculationSheet->client_id)->get();
            if ($approveFlow->isNotEmpty()) {
                foreach($approveFlow as $item) {
                    $userFlow = ApproveFlowUser::where('approve_flow_id', $item['id'])->get();
                    if($userFlow->isEmpty()){
                        throw new CustomException(
                            __("notifications.request_config_payroll"),
                            'HttpException'
                        );
                    }
                }
            } else {
                throw new CustomException(
                    __("notifications.request_config_payroll"),
                    'HttpException'
                );
            }
        }
    }

    public function updating(CalculationSheet $calculationSheet)
    {
        // $changed = $calculationSheet->getOriginal("status") != $calculationSheet->status;
        // if ($changed) {
        //     $this->updateCalculationSheetStatus($calculationSheet);
        // }
        // $this->updateClientEmployeeSalaries($calculationSheet);
    }

    public function updated(CalculationSheet $calculationSheet)
    {
        $changed = $calculationSheet->getOriginal("status") != $calculationSheet->status;

        if ($changed) {
            $this->updateCalculationSheetStatus($calculationSheet);
        }
        $this->updateClientEmployeeSalaries($calculationSheet);
    }

    /**
     * @throws HumanErrorException
     */
    public function deleting(CalculationSheet $calculationSheet)
    {
        $this->checkApproveBeforeDelete($calculationSheet->id);
    }

    /**
     * Handle the calculation sheet "deleted" event.
     *
     * @param CalculationSheet $calculationSheet
     *
     * @return void
     */
    public function deleted(CalculationSheet $calculationSheet)
    {
        CalculationSheetClientEmployee::where('calculation_sheet_id', $calculationSheet->id)->delete();
        $this->deleteApprove('App\Models\CalculationSheet', $calculationSheet->id);
    }

    /**
     * Handle the calculation sheet "restored" event.
     *
     * @param CalculationSheet $calculationSheet
     *
     * @return void
     */
    public function restored(CalculationSheet $calculationSheet)
    {
        //
    }

    /**
     * Handle the calculation sheet "force deleted" event.
     *
     * @param CalculationSheet $calculationSheet
     *
     * @return void
     * @throws HumanErrorException
     */
    public function forceDeleted(CalculationSheet $calculationSheet)
    {
        CalculationSheetClientEmployee::where('calculation_sheet_id', $calculationSheet->id)->delete();
        CalculationSheetVariable::where('calculation_sheet_id', $calculationSheet->id)->delete();
    }

    protected function updateClientEmployeeSalaries(CalculationSheet $calculationSheet)
    {
        if ($calculationSheet->status == 'client_approved') {
            $calculationSheetClientEmployees = CalculationSheetClientEmployee::where('calculation_sheet_id', $calculationSheet->id)
                ->with('clientEmployee')
                ->with('calculationSheet')
                ->get();

            if (!empty($calculationSheetClientEmployees)) {
                foreach ($calculationSheetClientEmployees as $c) {
                    $calculationSheetVariable = CalculationSheetVariable::where('calculation_sheet_id', $calculationSheet->id)
                        ->where('client_employee_id', $c->client_employee_id)
                        ->where('variable_name', 'F_HOUR_WAGES')
                        ->first();
                    $hour_wage = 0;
                    if ($calculationSheetVariable) {
                        $hour_wage = $calculationSheetVariable->variable_value;
                    }
                    ClientEmployee::whereId($c->client_employee_id)->update([
                        'hour_wage' => $hour_wage,
                    ]);

                    $created_at = $c->calculationSheet['year'] . '-' . str_pad($c->calculationSheet['month'], 2, '0', STR_PAD_LEFT) . '-01 00:00:00';

                    ClientEmployeeSalary::create([
                        'client_id' => $c->clientEmployee['client_id'],
                        'client_employee_id' => $c->client_employee_id,
                        'salary' => $c->calculated_value,
                        'title' => $c->clientEmployee['title'],
                        'position' => $c->clientEmployee['position'],
                        'department' => $c->clientEmployee['department'],
                        'created_at' => $created_at,
                    ]);
                }
            }
        }
    }

    protected function updateCalculationSheetStatus(CalculationSheet $calculationSheet)
    {
        if ($calculationSheet->status == 'new') {
            $LIMIT = ClientHelper::getClientEmployeeLimit($calculationSheet->client_id);

            $clientPayrollHeadCount = ClientPayrollHeadCount::select('*')
                ->where('client_id', $calculationSheet->client_id)
                ->where('month', $calculationSheet->month)
                ->where('year', $calculationSheet->year)->first();

            if (!$clientPayrollHeadCount || $clientPayrollHeadCount->total <= $LIMIT) {
                $assignments = CalculationSheetTemplateAssignment::query()
                    ->where('template_id', $calculationSheet->calculation_sheet_template_id)
                    ->get();
                $calculationSheetEmployees = $assignments->pluck('client_employee_id');

                ClientHelper::updatePayrollHeadcount(
                    $calculationSheet->client_id,
                    $calculationSheet->month,
                    $calculationSheet->year,
                    $calculationSheetEmployees->all()
                );
            } else {
                if (!$calculationSheet->is_internal) {
                    $calculationSheet->status = 'limited';
                }
            }
        } elseif ($calculationSheet->status == 'client_approved') {
            if ($calculationSheet->enable_show_payslip_for_employee && $calculationSheet->is_send_mail_payslip) {
                if ($calculationSheet->payslip_date) {
                    $now_timestamp = Carbon::now(Constant::TIMESHEET_TIMEZONE)->timestamp;
                    $date_timestamp = Carbon::parse($calculationSheet->payslip_date. ' 06:00:00', Constant::TIMESHEET_TIMEZONE)->timestamp;
                    // thời gian hiển thị payslip cho nhân viên nhỏ hơn thời gian hiện tại
                    if($now_timestamp < $date_timestamp) {
                        $delay = $date_timestamp - $now_timestamp;
                        ProcessScheduleMail::dispatch(CalculationSheet::class, $calculationSheet->id, 'processSendPayslipMailForEmployees')->delay($delay);
                    } else {
                        $calculationSheet->processSendPayslipMailForEmployees();
                    }

                } else {
                    $calculationSheet->processSendPayslipMailForEmployees();
                }
            }

            $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $calculationSheet->client_id)->first();

            if (!$clientWorkflowSetting->enable_salary_payment) {
                $calculationSheet->status = 'paid';
                $calculationSheet->save();
            }

            WorkTimeRegisterTimesheet::whereHas('workTimeRegisterLog.calculationSheetClientEmployee', function($q) use ($calculationSheet) {
                $q->where('calculation_sheet_id', $calculationSheet->id);
            })->update([
                'month_lock' => $calculationSheet->month,
                'year_lock' =>  $calculationSheet->year
            ]);

        } elseif ($calculationSheet->status == 'client_review') {

            $hasApprove = Approve::where('target_id', $calculationSheet->id)
                                    ->where('client_id', $calculationSheet->client_id)
                                    ->where('type', 'CLIENT_REQUEST_PAYROLL')->first();

            if($hasApprove) return;

            $defaultClientEmployeeGroup = '0';

            $approveFlow = ApproveFlow::where('client_id', $calculationSheet->client_id)
                ->where('flow_name', 'CLIENT_REQUEST_PAYROLL')
                ->where('group_id', $defaultClientEmployeeGroup)
                ->where('step', 1)
                ->first();

            $approveGroup = ApproveGroup::create([
                'type' => 'CLIENT_REQUEST_PAYROLL',
                'client_id' => $calculationSheet->client_id
            ]);

            if (!empty($approveFlow)) {
                $flowUsers = ApproveFlowUser::select('user_id')
                    ->where('approve_flow_id', $approveFlow->id)
                    ->orderBy('created_at', 'ASC')
                    ->get();

                if (!empty($flowUsers)) {

                    $assigneeId = $calculationSheet->prefered_reviewer_id ? $calculationSheet->prefered_reviewer_id : $flowUsers[0]->user_id;

                    Approve::create([
                        'type' => 'CLIENT_REQUEST_PAYROLL',
                        'client_id' => $calculationSheet->client_id,
                        'original_creator_id' => $calculationSheet->creator_id,
                        'creator_id' => $calculationSheet->creator_id,
                        'assignee_id' => $assigneeId,
                        'step' => 1,
                        'target_type' => 'App\Models\CalculationSheet',
                        'target_id' => $calculationSheet->id,
                        'content' => json_encode(['id' => $calculationSheet->id]),
                        'approve_group_id' => $approveGroup->id,
                        'client_employee_group_id' => $defaultClientEmployeeGroup
                    ]);
                }
            } else {
                $manager = ClientEmployee::where('client_id', $calculationSheet->client_id)
                    ->where('role', 'manager')
                    ->first();

                if (!empty($manager)) {
                    Approve::create([
                        'type' => 'CLIENT_REQUEST_PAYROLL',
                        'client_id' => $calculationSheet->client_id,
                        'creator_id' => $calculationSheet->creator_id,
                        'assignee_id' => $manager->user_id,
                        'original_creator_id' => $calculationSheet->creator_id,
                        'step' => 1,
                        'target_type' => 'App\Models\CalculationSheet',
                        'target_id' => $calculationSheet->id,
                        'content' => json_encode(['id' => $calculationSheet->id]),
                        'approve_group_id' => $approveGroup->id,
                        'client_employee_group_id' => $defaultClientEmployeeGroup
                    ]);
                }
            }
        }
    }
}
