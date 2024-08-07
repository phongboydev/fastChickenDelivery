<?php

namespace App\Providers;

use App\Models\Allowance;
use App\Models\AllowanceGroup;
use App\Models\Client;
use App\Models\ClientEmployeeForeignVisa;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeSalaryHistory;
use App\Models\ClientSettingConditionCompare;
use App\Models\ContractSignStep;
use App\Models\HeadcountPeriodSetting;
use App\Models\KnowledgeQuestion;
use App\Models\LeaveCategory;
use App\Models\OvertimeCategory;
use App\Models\PaymentRequestExportTemplate;
use App\Models\PowerBiReport;
use App\Models\TimeSheetEmployeeExport;
use App\Models\TimesheetShift;
use App\Models\TimesheetShiftMapping;
use App\Models\WebFeatureSlider;
use App\Policies\ClientPolicy;
use App\Policies\ClientSettingConditionComparePolicy;
use App\Policies\ContractSignStepPolicy;
use App\Policies\HeadcountPeriodSettingPolicy;
use App\Policies\KnowledgeQuestionPolicy;
use App\Policies\ClientEmployeeLeaveManagementPolicy;
use App\Policies\LeaveCategoryPolicy;
use App\Policies\OvertimeCategoryPolicy;
use App\Policies\PaymentRequestExportTemplatePolicy;
use App\Policies\PowerBiReportPolicy;
use App\Policies\TimeSheetEmployeeExportPolicy;
use App\Policies\TimeSheetShiftMappingPolicy;
use App\Policies\TimesheetShiftPolicy;
use App\Policies\WebFeatureSliderPolicy;
use App\Policies\WebVersionPolicy;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Policies\CalculationSheetPolicy;
use App\Models\CalculationSheet;
use App\Policies\ClientEmployeePolicy;
use App\Models\ClientEmployee;
use App\Policies\CalculationSheetClientEmployeePolicy;
use App\Models\CalculationSheetClientEmployee;
use App\Policies\CalculationSheetTemplatePolicy;
use App\Models\CalculationSheetTemplate;
use App\Policies\CalculationSheetVariablePolicy;
use App\Models\CalculationSheetVariable;
use App\Policies\ClientCustomVariablePolicy;
use App\Models\ClientCustomVariable;
use App\Policies\ClientEmployeeCustomVariablePolicy;
use App\Models\ClientEmployeeCustomVariable;
use App\Policies\FormulaPolicy;
use App\Models\Formula;
use App\Policies\ClientEmployeeEarlyLateRequestPolicy;
use App\Models\ClientEmployeeEarlyLateRequest;
use App\Policies\ClientEmployeeLeaveRequestPolicy;
use App\Models\ClientEmployeeLeaveRequest;
use App\Policies\ClientEmployeeOvertimeRequestPolicy;
use App\Models\ClientEmployeeOvertimeRequest;
use App\Policies\IglocalAssignmentPolicy;
use App\Models\IglocalAssignment;
use App\Policies\IglocalEmployeePolicy;
use App\Models\IglocalEmployee;
use App\Policies\TimesheetPolicy;
use App\Models\Timesheet;
use App\Policies\UserPolicy;
use App\User;
use App\Policies\SupportTicketPolicy;
use App\Models\SupportTicket;
use App\Policies\SupportTicketCommentPolicy;
use App\Models\SupportTicketComment;
use App\Models\ClientWorkflowSetting;
use App\Policies\ClientWorkflowSettingPolicy;
use App\Models\Approve;
use App\Models\ClientAssignment;
use App\Policies\ApprovePolicy;
use App\Policies\ClientAssignmentPolicy;
use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use App\Models\WorkScheduleGroup;
use App\Policies\WorkScheduleGroupPolicy;
use App\Models\WorkScheduleGroupTemplate;
use App\Policies\WorkScheduleGroupTemplatePolicy;
use App\Models\DebitNote;
use App\Policies\DebitNotePolicy;
use App\Models\Slider;
use App\Policies\SliderPolicy;
use App\Models\MobileVersion;
use App\Policies\MobileVersionPolicy;
use App\Models\SocialSecurityProfile;
use App\Policies\SocialSecurityProfilePolicy;
use App\Models\CalculationSheetTemplateAssignment;
use App\Policies\CalculationSheetTemplateAssignmentPolicy;
use App\Models\YearHoliday;
use App\Policies\YearHolidayPolicy;
use App\Models\CalculationSheetExportTemplate;
use App\Policies\CalculationSheetExportTemplatePolicy;
use App\Models\SocialSecurityClaim;
use App\Policies\SocialSecurityClaimPolicy;
use App\Models\PayrollAccountantTemplate;
use App\Policies\PayrollAccountantTemplatePolicy;
use App\Models\PayrollAccountantExportTemplate;
use App\Policies\PayrollAccountantExportTemplatePolicy;
use App\Models\ClientLog;
use App\Policies\ClientLogPolicy;
use App\Models\ClientEmployeeContract;
use App\Policies\ClientEmployeeContractPolicy;
use App\Models\ClientEmployeeLocationHistory;
use App\Policies\ClientEmployeeLocationHistoryPolicy;
use App\Policies\ClientEmployeeForeignVisaPolicy;
use App\Policies\ClientEmployeeForeignWorkpermitPolicy;
use App\Models\ClientWifiCheckinSpot;
use App\Policies\ClientWifiCheckinSpotPolicy;
use App\Models\ClientLocationCheckin;
use App\Policies\ClientLocationCheckinPolicy;
use App\Models\EmailTemplate;
use App\Policies\EmailTemplatePolicy;
use App\Models\TrainingSeminar;
use App\Policies\TrainingSeminarPolicy;
use App\Models\ClientAppliedDocument;
use App\Policies\ClientAppliedDocumentPolicy;
use App\Models\WorktimeRegister;
use App\Policies\WorktimeRegisterPolicy;
use App\Models\ClientEmployeeTrainingSeminar;
use App\Policies\ClientEmployeeTrainingSeminarPolicy;
use App\Models\ContractTemplate;
use App\Policies\ContractTemplatePolicy;
use App\Models\Contract;
use App\Policies\ContractPolicy;
use App\Models\ClientDepartment;
use App\Policies\ClientDepartmentPolicy;
use App\Models\ReportPit;
use App\Policies\ReportPitPolicy;

use App\Models\Comment;
use App\Models\EvaluationGroup;
use App\Models\Evaluation;
use App\Policies\AllowanceGroupPolicy;
use App\Policies\AllowancePolicy;
use App\Policies\CommentPolicy;
use App\Models\ApproveFlow;
use App\Policies\ApproveFlowPolicy;
use App\Models\ApproveFlowUser;
use App\Models\HanetDevice;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Policies\ApproveFlowUserPolicy;
use App\Models\JobboardApplication;
use App\Policies\JobboardApplicationPolicy;
use App\Models\JobboardJob;
use App\Models\JobboardSetting;
use App\Policies\JobboardJobPolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\EvaluationGroupPolicy;
use App\Policies\EvaluationUserPolicy;
use App\Policies\JobboardSettingPolicy;
use App\Models\ReportPayroll;
use App\Policies\ReportPayrollPolicy;
use App\Models\HanetSetting;
use App\Policies\HanetDevicePolicy;
use App\Policies\HanetPersonPolicy;
use App\Policies\HanetPlacePolicy;
use App\Policies\HanetSettingPolicy;
use App\Models\JobboardAssignment;
use App\Policies\JobboardAssignmentPolicy;
use App\Models\PaidLeaveChange;
use App\Policies\PaidLeaveChangePolicy;
use App\Models\PaymentOnBehalfServiceInformation;
use App\Policies\PaymentOnBehalfServiceInformationPolicy;
use App\Models\SocialSecurityClaimTracking;
use App\Policies\SocialSecurityClaimTrackingPolicy;
use App\Models\ApproveGroup;
use App\Policies\ApproveGroupPolicy;
use App\Models\DataImport;
use App\Policies\DataImportPolicy;
use App\Models\SocialSecurityProfileRequest;
use App\Policies\SocialSecurityProfileRequestPolicy;
use App\Models\SocialSecurityProfileHistory;
use App\Policies\SocialSecurityProfileHistoryPolicy;
use App\Models\WorktimeRegisterCategory;
use App\Policies\WorktimeRegisterCategoryPolicy;
use App\Models\DataImportHistory;
use App\Policies\DataImportHistoryPolicy;
use App\Models\ClientEmployeeGroup;
use App\Policies\ClientEmployeeGroupPolicy;
use App\Models\ClientEmployeeGroupAssignment;
use App\Policies\ClientEmployeeGroupAssignmentPolicy;
use App\Policies\ClientEmployeeSalaryHistoryPolicy;
use App\Policies\ClientEmployeePositionHistoryPolicy;
use App\Models\ClientEmployeePositionHistory;
use App\Models\Bank;
use App\Policies\BankPolicy;
use App\Models\Supplier;
use App\Policies\SupplierPolicy;
use App\Models\Beneficiary;
use App\Policies\BeneficiaryPolicy;
use App\Models\ClientEmployeePayslipComplaint;
use App\Policies\ClientEmployeePayslipComplaintPolicy;
use App\Models\ClientEmployeeDependentRequest;
use App\Policies\ClientEmployeeDependentRequestPolicy;
use App\Models\ClientEmployeeDependentApplication;
use App\Policies\ClientEmployeeDependentApplicationPolicy;
use App\Models\ClientEmployeeDependent;
use App\Policies\ClientEmployeeDependentPolicy;
use App\Models\UnpaidLeaveChange;
use App\Policies\UnpaidLeaveChangePolicy;

use App\Models\SocialSecurityAccount;
use App\Policies\SocialSecurityAccountPolicy;
use App\Models\ProvinceHospital;
use App\Models\WebVersion;
use App\Policies\ProvinceHospitalPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        ClientEmployee::class => ClientEmployeePolicy::class,
        Client::class => ClientPolicy::class,
        CalculationSheet::class => CalculationSheetPolicy::class,
        CalculationSheetClientEmployee::class => CalculationSheetClientEmployeePolicy::class,
        CalculationSheetTemplate::class => CalculationSheetTemplatePolicy::class,
        CalculationSheetVariable::class => CalculationSheetVariablePolicy::class,
        ClientCustomVariable::class => ClientCustomVariablePolicy::class,
        ClientEmployeeCustomVariable::class => ClientEmployeeCustomVariablePolicy::class,
        Formula::class => FormulaPolicy::class,
        ClientEmployeeEarlyLateRequest::class => ClientEmployeeEarlyLateRequestPolicy::class,
        ClientEmployeeLeaveRequest::class => ClientEmployeeLeaveRequestPolicy::class,
        ClientEmployeeOvertimeRequest::class => ClientEmployeeOvertimeRequestPolicy::class,
        IglocalAssignment::class => IglocalAssignmentPolicy::class,
        IglocalEmployee::class => IglocalEmployeePolicy::class,
        Timesheet::class => TimesheetPolicy::class,
        TimesheetShiftMapping::class => TimeSheetShiftMappingPolicy::class,
        User::class => UserPolicy::class,
        SupportTicket::class => SupportTicketPolicy::class,
        SupportTicketComment::class => SupportTicketCommentPolicy::class,
        ClientWorkflowSetting::class => ClientWorkflowSettingPolicy::class,
        Approve::class => ApprovePolicy::class,
        ClientAssignment::class => ClientAssignmentPolicy::class,
        WorkSchedule::class => WorkSchedulePolicy::class,
        WorkScheduleGroup::class => WorkScheduleGroupPolicy::class,
        WorkScheduleGroupTemplate::class => WorkScheduleGroupTemplatePolicy::class,
        KnowledgeQuestion::class => KnowledgeQuestionPolicy::class,
        DebitNote::class => DebitNotePolicy::class,
        Slider::class => SliderPolicy::class,
        MobileVersion::class => MobileVersionPolicy::class,
        SocialSecurityProfile::class => SocialSecurityProfilePolicy::class,
        CalculationSheetTemplateAssignment::class => CalculationSheetTemplateAssignmentPolicy::class,
        YearHoliday::class => YearHolidayPolicy::class,
        CalculationSheetExportTemplate::class => CalculationSheetExportTemplatePolicy::class,
        SocialSecurityClaim::class => SocialSecurityClaimPolicy::class,
        PayrollAccountantTemplate::class => PayrollAccountantTemplatePolicy::class,
        PayrollAccountantExportTemplate::class => PayrollAccountantExportTemplatePolicy::class,
        ClientLog::class => ClientLogPolicy::class,
        ClientEmployeeContract::class => ClientEmployeeContractPolicy::class,
        ClientEmployeeLocationHistory::class => ClientEmployeeLocationHistoryPolicy::class,
        ClientEmployeeForeignVisa::class => ClientEmployeeForeignVisaPolicy::class,
        ClientEmployeeForeignWorkpermitPolicy::class => ClientEmployeeForeignWorkpermitPolicy::class,
        ClientWifiCheckinSpot::class => ClientWifiCheckinSpotPolicy::class,
        ClientLocationCheckin::class => ClientLocationCheckinPolicy::class,
        EmailTemplate::class => EmailTemplatePolicy::class,
        TrainingSeminar::class => TrainingSeminarPolicy::class,
        ClientAppliedDocument::class => ClientAppliedDocumentPolicy::class,
        Comment::class => CommentPolicy::class,
        WorktimeRegister::class => WorktimeRegisterPolicy::class,
        ClientEmployeeTrainingSeminar::class => ClientEmployeeTrainingSeminarPolicy::class,
        Contract::class => ContractPolicy::class,
        ContractTemplate::class => ContractTemplatePolicy::class,
        ClientDepartment::class => ClientDepartmentPolicy::class,
        Allowance::class => AllowancePolicy::class,
        AllowanceGroup::class => AllowanceGroupPolicy::class,
        ApproveFlowUser::class => ApproveFlowUserPolicy::class,
        ApproveFlow::class => ApproveFlowPolicy::class,
        JobboardApplication::class => JobboardApplicationPolicy::class,
        JobboardJob::class => JobboardJobPolicy::class,
        EvaluationGroup::class => EvaluationGroupPolicy::class,
        Evaluation::class => EvaluationPolicy::class,
        JobboardSetting::class => JobboardSettingPolicy::class,
        ReportPayroll::class => ReportPayrollPolicy::class,
        ReportPit::class => ReportPitPolicy::class,
        HanetSetting::class => HanetSettingPolicy::class,
        HanetDevice::class => HanetDevicePolicy::class,
        HanetPlace::class => HanetPlacePolicy::class,
        HanetPerson::class => HanetPersonPolicy::class,
        JobboardAssignment::class => JobboardAssignmentPolicy::class,
        PaidLeaveChange::class => PaidLeaveChangePolicy::class,
        PaymentOnBehalfServiceInformation::class => PaymentOnBehalfServiceInformationPolicy::class,
        SocialSecurityClaimTracking::class => SocialSecurityClaimTrackingPolicy::class,
        ApproveGroup::class => ApproveGroupPolicy::class,
        DataImport::class => DataImportPolicy::class,
        SocialSecurityProfileRequest::class => SocialSecurityProfileRequestPolicy::class,
        SocialSecurityProfileHistory::class => SocialSecurityProfileHistoryPolicy::class,
        WorktimeRegisterCategory::class => WorktimeRegisterCategoryPolicy::class,
        DataImportHistory::class => DataImportHistoryPolicy::class,
        ClientEmployeeGroup::class => ClientEmployeeGroupPolicy::class,
        ClientEmployeeGroupAssignment::class => ClientEmployeeGroupAssignmentPolicy::class,
        TimesheetShift::class => TimesheetShiftPolicy::class,
        PowerBiReport::class => PowerBiReportPolicy::class,
        ContractSignStep::class => ContractSignStepPolicy::class,
        TimeSheetEmployeeExport::class => TimeSheetEmployeeExportPolicy::class,
        ClientEmployeeSalaryHistory::class => ClientEmployeeSalaryHistoryPolicy::class,
        ClientEmployeePositionHistory::class => ClientEmployeePositionHistoryPolicy::class,
        Bank::class => BankPolicy::class,
        Supplier::class => SupplierPolicy::class,
        Beneficiary::class => BeneficiaryPolicy::class,
        HeadcountPeriodSetting::class => HeadcountPeriodSettingPolicy::class,
        LeaveCategory::class => LeaveCategoryPolicy::class,
        OvertimeCategory::class => OvertimeCategoryPolicy::class,
        ClientEmployeePayslipComplaint::class => ClientEmployeePayslipComplaintPolicy::class,
        ClientEmployeeDependentRequest::class => ClientEmployeeDependentRequestPolicy::class,
        ClientEmployeeDependentApplication::class => ClientEmployeeDependentApplicationPolicy::class,
        ClientEmployeeDependent::class => ClientEmployeeDependentPolicy::class,
        SocialSecurityAccount::class => SocialSecurityAccountPolicy::class,
        ClientEmployeeLeaveManagement::class => ClientEmployeeLeaveManagementPolicy::class,
        ProvinceHospital::class => ProvinceHospitalPolicy::class,
        ClientSettingConditionCompare::class => ClientSettingConditionComparePolicy::class,
        PaymentRequestExportTemplate::class => PaymentRequestExportTemplatePolicy::class,
        UnpaidLeaveChange::class => UnpaidLeaveChangePolicy::class,
        WebVersion::class => WebVersionPolicy::class,
        WebFeatureSlider::class => WebFeatureSliderPolicy::class,
    ];
    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Passport::routes();
        Passport::withoutCookieSerialization();
        // Passport::$pruneRevokedTokens = true;
        Passport::$ignoreCsrfToken = true;
        Passport::tokensExpireIn(
            now()->addHours(config('app.env') !== 'production' ? 1000 : 999)
        );
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
