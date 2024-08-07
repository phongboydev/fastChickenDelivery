<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Notifications\ClientEmployeePayslipNotification;
use App\Support\Constant;
use App\Support\MediaTrait;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Znck\Eloquent\Traits\BelongsToThrough;
use Illuminate\Support\Facades\App;


/**
 * All flow can be found here: iglocal/pages/khach-hang/thiet-lap-flow.vue
 */
class CalculationSheet extends Model implements HasMedia
{

    use InteractsWithMedia, MediaTrait, BelongsToThrough;
    use UsesUuid, LogsActivity, HasAssignment, SoftDeletes;

    protected static array $logAttributes = ['*'];

    protected $table = 'calculation_sheets';

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
     * @var array
     */
    protected $fillable = [
        'client_id',
        'name',
        'type',
        'fomulas',
        'multiple_variables',
        'payment_period',
        'display_columns',
        'payslip_columns_setting',
        'payslip_accountant_columns_setting',
        'payslip_html_template',
        'template_export_id',
        'date_from',
        'date_to',
        'month',
        'year',
        'status',
        'created_at',
        'updated_at',
        'calculation_sheet_template_id',
        'is_multilingual',
        'payslip_html_template_ja',
        'payslip_html_template_vi',
        'payslip_html_template_en',
        'approved_comment',
        'enable_notification_new_payroll',
        'enable_show_payslip_for_employee',
        'payslip_date',
        'prefered_reviewer_id',
        'payslip_complaint_deadline',
        'is_send_mail_payslip'
    ];

    protected $attributes = [
        'status' => Constant::NEW_STATUS,
    ];

    // TODO để cast thành array, phải replace hết các chỗ đang stringify, parse payslip_accountant_columns_setting JSON
    // TODO Sửa schema String -> JSON
    protected $casts = [
    //     'payslip_accountant_columns_setting' => 'array',
        'multiple_variables' => 'array',
    ];

    public function getMediaModel()
    {
        return $this->getMedia('CalculationSheet');
    }

    public function getCsvPathAttribute()
    {
        $media = $this->getMediaModel();

        if (count($media) > 0) {
            foreach ($media as $m) {
                if ($m->extension == 'csv') {
                    return $this->getPublicTemporaryUrl($m);
                }
            }
        } else {
            return '';
        }
    }

    public function getExcelPathAttribute()
    {
        $media = $this->getMediaModel();

        if (count($media) > 0) {
            foreach ($media as $m) {
                if ($m->extension == 'xlsx') {
                    return $this->getPublicTemporaryUrl($m);
                }
            }
        } else {
            return '';
        }
    }

    public function getPdfPathAttribute()
    {
        $media = $this->getMedia('pdf');

        if (count($media) > 0) {
            foreach ($media as $m) {
                if ($m->extension == 'pdf') {
                    return $this->getPublicTemporaryUrl($m);
                }
            }
        } else {
            return '';
        }
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    /**
     * @return HasMany
     */
    public function calculationSheetClientEmployees()
    {
        return $this->hasMany('App\Models\CalculationSheetClientEmployee');
    }

    public function clientEmployees()
    {
        return $this->hasManyThrough(
            ClientEmployee::class,
            CalculationSheetClientEmployee::class,
            'calculation_sheet_id',
            'id',
            'id',
            'client_employee_id'
        );
    }

    /**
     * @return HasMany
     */
    public function calculationSheetVariables()
    {
        return $this->hasMany('App\Models\CalculationSheetVariable');
    }

    public function templateExport()
    {
        return $this->belongsTo('App\Models\CalculationSheetExportTemplate', 'template_export_id');
    }

    /**
     * Get all of the post's comments.
     */
    public function approves()
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {

            $normalPermissions = ["manage-payroll"];
            $advancedPermissions = ["advanced-manage-payroll-list", "advanced-manage-payroll-info"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, []);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow(), null, true)) {
                return $query->where('client_id', '=', $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {
            $query = $query->whereHas('client');
            $approveFlow = ApproveFlow::select('*')->where('flow_name', 'INTERNAL_MANAGE_CALCULATION')->where('group_id', '0')->get();

            if ($approveFlow->isNotEmpty()) {
                $approveFlows = $approveFlow->pluck('id')->all();

                $userFlow = ApproveFlowUser::select('*')->whereIn('approve_flow_id', $approveFlows)->get();

                if ($approveFlow->isNotEmpty()) {
                    $userFlows = $userFlow->pluck('user_id')->all();

                    if (in_array($user->id, $userFlows)) {
                        return $query;
                    }
                }
            }

            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $role == Constant::ROLE_INTERNAL_ACCOUNTANT || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->belongToClientAssignedTo($user->iGlocalEmployee);
        }
    }

    public function calculationSheetTemplate(): BelongsTo
    {
        return $this->belongsTo(CalculationSheetTemplate::class);
    }

    public function getPayrollAccountantExportTemplateIdAttribute()
    {
        $columnSettings = json_decode($this->payslip_accountant_columns_setting, true);
        return isset($columnSettings["template"]) ? $columnSettings["template"] : null;
    }

    public function getPayrollAccountantExportValuesAttribute()
    {
        $columnSettings = json_decode($this->payslip_accountant_columns_setting, true);
        return isset($columnSettings["values"]) ? $columnSettings["values"] : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "creator_id");
    }

    public function clientEmployeePayslipComplaint()
    {
        return $this->belongsToThrough(ClientEmployeePayslipComplaint::class, CalculationSheetClientEmployee::class);
    }

    public function processSendPayslipMailForEmployees($params = [])
    {
        $calClientEmployees = CalculationSheetClientEmployee::select('*')
            ->where(
                'calculation_sheet_id',
                $this->id
            )
            ->with('clientEmployee')
            ->with('user')
            ->get();

        // Send payroll notification to employee
        $listEmployeeReceiveNotificationIds = json_decode($this->list_employee_notify_ids);
        foreach ($calClientEmployees as $calClientEmployee) {
            /** @var $user User */
            $user = $calClientEmployee->user;
            $clientEmployee = $calClientEmployee->clientEmployee;
            if (!$user) {
                // skip notification if user not existed
                continue;
            }
            if (
                $listEmployeeReceiveNotificationIds !== null &&
                !in_array($clientEmployee->id, $listEmployeeReceiveNotificationIds)
            ) {
                // skip notification if list_employee_notify_ids defined and not in the list
                continue;
            }

            $user->loadUserLocale();
            $user->pushDeviceNotification(
                __('device_notification.new_payslip.title'),
                __('device_notification.new_payslip.content'),
                [
                    'type' => 'new_payslip',
                    'calculation_sheet_id' => $this->id,
                    'client_employee_id' => $clientEmployee->id,
                ],
            );
            $user->notify(new ClientEmployeePayslipNotification(
                $clientEmployee,
                $calClientEmployee,
                $this
            ));
        }
    }
}
