<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string $fomulas
 * @property string $payment_period
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 */
class CalculationSheetTemplate extends Model
{

    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'calculation_sheet_templates';

    public $timestamps = true;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string[]
     */
    // TODO để cast thành array, phải replace hết các chỗ đang stringify, parse payslip_accountant_columns_setting JSON
    // TODO Sửa schema String -> JSON
    protected $casts = [
        //     'payslip_accountant_columns_setting' => 'array',
        'multiple_variables' => 'array',
    ];

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'name',
        'fomulas',
        'multiple_variables',
        'payment_period',
        'display_columns',
        'payslip_columns_setting',
        'payslip_accountant_columns_setting',
        'payslip_html_template',
        'created_at',
        'updated_at',
        'is_enabled',
        'enable_cross_ot_calculation',
        'enable_show_payslip_for_employee',
        'enable_notification_new_payroll',
        'cross_ot_start_date',
        'cross_ot_end_date',
        'cross_ot_start_month',
        'template_export_id',
        'is_multilingual',
        'payslip_html_template_ja',
        'payslip_html_template_vi',
        'payslip_html_template_en',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @param $query
     * @param $leaderId
     */
    public function scopeEmployeeOf($query, $templateId)
    {
        $query->whereHas('assignments', function (Builder $query) use ($templateId) {
            $model = new CalculationSheetTemplateAssignment();
            $query->where("{$model->getTable()}.template_id", $templateId);
        });
    }

    public function assignments()
    {
        return $this->hasMany(CalculationSheetTemplateAssignment::class, 'template_id');
    }

    public function scopeHasAssignmentsWith($query, $templateId)
    {
        return $query->where(function ($query) use ($templateId) {
            $query->employeeOf($templateId);
        });
    }

    public function clientEmployees()
    {
        return $this->belongsToMany(
            ClientEmployee::class,
            'calculation_sheet_template_assignments',
            'template_id'
        );
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission('manage-payroll')) {
                return $query->where("{$this->getTable()}.client_id", $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) use ($user) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", $user->iGlocalEmployee->id);
            });
        }
    }

    /**
     * @param string $name
     * @param string $dateFrom
     * @param string $dateTo
     * @param string $otherFrom
     * @param string $otherTo
     * @param string $month
     * @param string $year
     *
     * @return CalculationSheet
     */
    public function createCalculationSheet(
        string $name,
        string $dateFrom,
        string $dateTo,
        string $otherFrom,
        string $otherTo,
        string $month,
        string $year,
        string $listEmployeeNotifyIds,
        bool $isInternal,
        string $preferedReviewer,
        string $payslipDate = NULL,
        string $payslip_complaint_deadline = NULL,
        bool $isSendMailPayslip = true
    ): CalculationSheet {
        $cs = new CalculationSheet();
        $cs->client_id = $this->client_id;
        $cs->name = $name;
        $cs->fomulas = $this->fomulas;
        $cs->multiple_variables = $this->multiple_variables;
        $cs->payment_period = $this->payment_period;
        $cs->display_columns = $this->display_columns;
        $cs->payslip_columns_setting = $this->payslip_columns_setting;
        $cs->payslip_accountant_columns_setting = $this->payslip_accountant_columns_setting;
        $cs->payslip_html_template_ja = $this->payslip_html_template_ja;
        $cs->payslip_html_template_vi = $this->payslip_html_template_vi;
        $cs->payslip_html_template_en = $this->payslip_html_template_en;
        $cs->is_multilingual = $this->is_multilingual;
        $cs->payslip_html_template = $this->payslip_html_template;
        $cs->template_export_id = $this->template_export_id;
        $cs->month = $month;
        $cs->year = $year;
        $cs->date_to = $dateTo;
        $cs->date_from = $dateFrom;
        $cs->other_from = $otherFrom ?? $dateFrom;
        $cs->other_to = $otherTo ?? $dateTo;
        $cs->payslip_date = $payslipDate;
        $cs->status = "creating";
        $cs->calculation_sheet_template_id = $this->id;
        $cs->approved_comment = "";
        $cs->enable_show_payslip_for_employee = $this->enable_show_payslip_for_employee;
        $cs->enable_notification_new_payroll = $this->enable_notification_new_payroll;
        $cs->list_employee_notify_ids = $listEmployeeNotifyIds;
        $cs->is_internal = $isInternal;
        $cs->prefered_reviewer_id = $preferedReviewer;
        $cs->payslip_complaint_deadline = $payslip_complaint_deadline;
        $cs->is_send_mail_payslip = $isSendMailPayslip;
        return $cs;
    }
}
