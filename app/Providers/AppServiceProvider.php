<?php

namespace App\Providers;

use App\Models\Approve;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientSettingConditionCompare;
use App\Models\Comment;
use App\Models\Evaluation;
use App\Models\HeadcountPeriodSetting;
use App\Models\LeaveCategory;
use App\Models\LibraryQuestionAnswer;
use App\Models\RecruitmentProcessAssignment;
use App\Models\SupportTicketComment;
use App\Models\WorkTimeRegisterPeriod;
use App\Observers\ClientSettingConditionComparerObserver;
use App\Observers\CommentObserver;
use App\Observers\EvaluationObserver;
use App\Observers\HeadcountPeriodSettingObserver;
use App\Observers\ClientEmployeeLeaveManagementObserver;
use App\Models\OvertimeCategory;
use App\Observers\LeaveCategoryObserver;
use App\Observers\LibraryQuestionAnswerObserver;
use App\Observers\RecruitmentProcessAssignmentObserver;
use App\Observers\SupportTicketCommentObserver;
use App\Observers\OvertimeCategoryObserver;
use App\Observers\UserObserver;
use App\Observers\WorkTimeRegisterPeriodObserver;
use App\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

use App\Models\CalculationSheetClientEmployee;
use App\Observers\CalculationSheetClientEmployeeObserver;

use App\Models\CalculationSheet;
use App\Models\CalculationSheetTemplate;
use App\Observers\CalculationSheetObserver;
use App\Models\SupportTicket;
use App\Observers\SupportTicketObserver;
use App\Models\ClientEmployee;
use App\Observers\ClientEmployeeObserver;
use App\Observers\ClientEmployeePositionHistoryObserver;
use App\Models\Timesheet;
use App\Observers\TimesheetObserver;
use App\Models\Client as ClientModel;
use App\Observers\ApproveObserver;
use App\Observers\ClientObserver;
use App\Models\WorkSchedule as WorkSchedule;
use App\Observers\WorkScheduleObserver;
use App\Models\SocialSecurityProfileRequest;
use App\Observers\CalculationSheetTemplateObserver;
use App\Observers\SocialSecurityProfileRequestObserver;
use App\Models\DebitNote;
use App\Observers\DebitNoteObserver;
use App\Models\ClientEmployeeContract;
use App\Observers\ClientEmployeeContractObserver;
use App\Models\ApproveFlowUser;
use App\Observers\ApproveFlowUserObserver;
use App\Models\ClientAppliedDocument;
use App\Models\IglocalEmployee;
use App\Observers\ClientAppliedDocumentObserver;
use App\Observers\IglocalEmployeeObserver;
use App\Models\WorktimeRegister;
use App\Observers\WorktimeRegisterObserver;
use App\Models\TimeChecking;
use App\Observers\TimeCheckingObserver;
use App\Models\SocialSecurityClaim;
use App\Observers\SocialSecurityClaimObserver;
use App\Models\AssignmentProject;
use App\Observers\AssignmentProjectObserver;
use App\Models\IglocalAssignment;
use App\Observers\IglocalAssignmentObserver;
use App\Models\ActionLog;
use App\Observers\ActionLogObserver;

use App\Models\CalculationSheetExportTemplate;
use App\Observers\CalculationSheetExportTemplateObserver;
use App\Models\ClientEmployeeDependent;
use App\Observers\ClientEmployeeDependentObserver;

use App\Models\WorkScheduleGroup;
use App\Observers\WorkScheduleGroupObserver;
use App\Models\JobboardApplication;
use App\Observers\JobboardApplicationObserver;
use App\Models\PaidLeaveChange;
use App\Observers\PaidLeaveChangeObserver;

use App\Models\ReportPit;
use App\Observers\ReportPitObserver;

use App\Models\WorktimeRegisterCategory;
use App\Observers\WorktimeRegisterCategoryObserver;

use App\Models\ApproveFlow;
use App\Observers\ApproveFlowObserver;

use App\Models\ClientEmployeeGroupAssignment;
use App\Observers\ClientEmployeeGroupAssignmentObserver;

use App\Models\ClientEmployeeGroup;
use App\Observers\ClientEmployeeGroupObserver;

use App\Models\TimesheetShift;
use App\Observers\TimesheetShiftObserver;

use App\Models\ClientCustomVariable;
use App\Observers\ClientCustomVariableObserver;

use App\Models\ClientEmployeeTrainingSeminar;
use App\Observers\ClientEmployeeTrainingSeminarObserver;
use App\Models\ClientWorkflowSetting;
use App\Observers\ClientWorkflowSettingObserver;

use App\Models\TrainingSeminarAttendance;
use App\Observers\TrainingSeminarAttendanceObserver;

use App\Models\Formula;
use App\Observers\FormulaObserver;

use App\Models\ClientDepartment;
use App\Observers\ClientDepartmentObserver;

use App\Models\ClientPosition;
use App\Observers\ClientPositionObserver;

use App\Models\ClientEmployeePayslipComplaint;
use App\Observers\ClientEmployeePayslipComplaintObserver;
use App\Models\SocialSecurityProfile;
use App\Observers\SocialSecurityProfileObserver;

use App\Observers\ClientEmployeeDependentApplicationObserver;
use App\Models\ClientEmployeeDependentApplication;

use App\Observers\ClientEmployeeDependentRequestObserver;
use App\Models\ClientEmployeeDependentRequest;

use App\Models\PaymentRequest;
use App\Observers\PaymentRequestObserver;
use App\Models\SocialSecurityAccount;
use App\Observers\SocialSecurityAccountObserver;

use App\Models\DependentRequestApplicationLink;
use App\Observers\DependentRequestApplicationLinkObserver;

use App\Support\Constant;
use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Validator;
use Raygun4php\RaygunClient;
use Raygun4php\Transports\GuzzleAsync;
use App\Rules\RequiredIfLevelIsDistrictOrWard;

use App\Models\UnpaidLeaveChange;
use App\Observers\UnpaidLeaveChangeObserver;

use App\Models\Contract;
use App\Models\JobboardApplicationEvaluation;
use App\Observers\ContractObserver;
use App\Observers\JobboardApplicationEvaluationObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        // Register the async transport.
        $this->app->singleton(GuzzleAsync::class, function ($app) {
            $httpClient = new GuzzleHttpClient([
                'base_uri' => config('services.raygun.api_url'),
                'headers' => [
                    'X-ApiKey' => config('services.raygun.api_key'),
                ]
            ]);

            return new GuzzleAsync($httpClient);
        });

        // Register the RaygunClient instance.
        $this->app->singleton(RaygunClient::class, function ($app) {
            return new RaygunClient($app->make(GuzzleAsync::class));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        CalculationSheet::observe(CalculationSheetObserver::class);
        CalculationSheetTemplate::observe(CalculationSheetTemplateObserver::class);
        CalculationSheetClientEmployee::observe(CalculationSheetClientEmployeeObserver::class);
        User::observe(UserObserver::class);
        SupportTicket::observe(SupportTicketObserver::class);
        ClientEmployee::observe(ClientEmployeeObserver::class);
        ClientEmployee::observe(ClientEmployeePositionHistoryObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        ClientModel::observe(ClientObserver::class);
        Approve::observe(ApproveObserver::class);
        WorkSchedule::observe(WorkScheduleObserver::class);
        SocialSecurityProfileRequest::observe(SocialSecurityProfileRequestObserver::class);
        DebitNote::observe(DebitNoteObserver::class);
        ClientEmployeeContract::observe(ClientEmployeeContractObserver::class);
        ApproveFlowUser::observe(ApproveFlowUserObserver::class);
        ClientAppliedDocument::observe(ClientAppliedDocumentObserver::class);
        IglocalEmployee::observe(IglocalEmployeeObserver::class);
        WorktimeRegister::observe(WorktimeRegisterObserver::class);
        TimeChecking::observe(TimeCheckingObserver::class);
        WorkTimeRegisterPeriod::observe(WorkTimeRegisterPeriodObserver::class);
        SocialSecurityClaim::observe(SocialSecurityClaimObserver::class);
        CalculationSheetExportTemplate::observe(CalculationSheetExportTemplateObserver::class);
        ClientEmployeeDependent::observe(ClientEmployeeDependentObserver::class);
        WorkScheduleGroup::observe(WorkScheduleGroupObserver::class);
        JobboardApplication::observe(JobboardApplicationObserver::class);
        PaidLeaveChange::observe(PaidLeaveChangeObserver::class);
        Passport::ignoreMigrations();
        AssignmentProject::observe(AssignmentProjectObserver::class);
        IglocalAssignment::observe(IglocalAssignmentObserver::class);
        ReportPit::observe(ReportPitObserver::class);
        WorktimeRegisterCategory::observe(WorktimeRegisterCategoryObserver::class);
        ApproveFlow::observe(ApproveFlowObserver::class);
        ActionLog::observe(ActionLogObserver::class);
        ClientEmployeeGroupAssignment::observe(ClientEmployeeGroupAssignmentObserver::class);
        ClientEmployeeGroup::observe(ClientEmployeeGroupObserver::class);
        TimesheetShift::observe(TimesheetShiftObserver::class);
        ClientCustomVariable::observe(ClientCustomVariableObserver::class);
        ClientEmployeeTrainingSeminar::observe(ClientEmployeeTrainingSeminarObserver::class);
        ClientWorkflowSetting::observe(ClientWorkflowSettingObserver::class);
        TrainingSeminarAttendance::observe(TrainingSeminarAttendanceObserver::class);
        Formula::observe(FormulaObserver::class);
        ClientDepartment::observe(ClientDepartmentObserver::class);
        ClientPosition::observe(ClientPositionObserver::class);
        HeadcountPeriodSetting::observe(HeadcountPeriodSettingObserver::class);
        LeaveCategory::observe(LeaveCategoryObserver::class);
        OvertimeCategory::observe(OvertimeCategoryObserver::class);
        Evaluation::observe(EvaluationObserver::class);
        ClientEmployeePayslipComplaint::observe(ClientEmployeePayslipComplaintObserver::class);
        Comment::observe(CommentObserver::class);
        SocialSecurityProfile::observe(SocialSecurityProfileObserver::class);
        PaymentRequest::observe(PaymentRequestObserver::class);
        ClientEmployeeDependentApplication::observe(ClientEmployeeDependentApplicationObserver::class);
        ClientEmployeeDependentRequest::observe(ClientEmployeeDependentRequestObserver::class);
        DependentRequestApplicationLink::observe(DependentRequestApplicationLinkObserver::class);
        SocialSecurityAccount::observe(SocialSecurityAccountObserver::class);
        ClientEmployeeLeaveManagement::observe(ClientEmployeeLeaveManagementObserver::class);
        SupportTicketComment::observe(SupportTicketCommentObserver::class);
        LibraryQuestionAnswer::observe(LibraryQuestionAnswerObserver::class);
        ClientSettingConditionCompare::observe(ClientSettingConditionComparerObserver::class);
        UnpaidLeaveChange::observe(UnpaidLeaveChangeObserver::class);
        Contract::observe(ContractObserver::class);
        JobboardApplicationEvaluation::observe(JobboardApplicationEvaluationObserver::class);
        RecruitmentProcessAssignment::observe(RecruitmentProcessAssignmentObserver::class);
        Validator::extend('username_exists', function ($attribute, $value, $parameters, $validator) {
            $data = $validator->getData();

            if (isset($data['id'])) {
                $user = User::where('id', $data['id'])->first();
                $isInternal = $user['is_internal'];
                $clientId = $user->client_id;
            } else {
                $isInternal = $data['is_internal'];
                $clientId   = $data['client_id'];
            }

            if ($isInternal) {
                $realUsername = Constant::INTERNAL_DUMMY_CLIENT_ID . '_' . $data['username'];
            } else {
                $realUsername = $clientId . '_' . $data['username'];
            }

            $query = User::query()->where('username', $realUsername);
            if (isset($data['id'])) {
                // is update
                $query->where('id', '<>', $data['id']);
            }

            return !$query->exists();
        });

        Validator::extend('required_if_level_district_or_ward', RequiredIfLevelIsDistrictOrWard::class);

        // Any requests to Raygun server will be send right before shutdown.
        register_shutdown_function([
            $this->app->make(GuzzleAsync::class), 'wait'
        ]);
    }
}
