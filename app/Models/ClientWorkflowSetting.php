<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\ConvertHelper;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_id
 * @property boolean $enable_overtime_request
 * @property boolean $enable_leave_request
 * @property boolean $enable_early_leave_request
 * @property boolean $enable_wifi_checkin
 * @property boolean $enable_training_seminar
 * @property boolean $enable_recruit_function
 */
class ClientWorkflowSetting extends Model
{
    use UsesUuid, HasAssignment;

    public static $FLEXIBLE_TIMESHEET_SETTING = [
        'enable_check_in_out'   => 1,
        'applied_core_time'     => 2,
        'applied_flexible_time' => 4,
    ];

    protected $table = 'client_workflow_settings';

    public $timestamps = false;

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
        'id',
        'client_id',
        'enable_overtime_request',
        'enable_leave_request',
        'enable_early_leave_request',
        'enable_timesheet_input',
        'enable_timesheet_rule',
        'enable_social_security_manage',
        'enable_salary_payment',
        'manage_user',
        'overtime_request_leader_approve',
        'overtime_request_manager_approve',
        'leave_request_leader_approve',
        'leave_request_manager_approve',
        'show_timesheet_for_customer',
        'enable_wifi_checkin',
        'enable_training_seminar',
        'enable_recruit_function',
        'enable_contract_reminder',
        'enable_security_2fa',
        'enable_create_payroll',
        'enable_setting_flow_permission',
        'client_employee_limit',
        'enable_client_project',
        'enable_request_payment',
        'enable_paid_leave_rule',
        'enable_location_checkin',
        'enable_cancel_approved_request',
        'timesheet_day_begin_mark',
        'enable_evaluate',
        'enable_auto_approve',
        'advanced_permission_flow',
        'advanced_approval_flow',
        'enable_auto_generate_ot',
        'approval_system_assigment_id',
        'flexible_timesheet_setting',
        'enable_flexible_request_setting',
        'number_of_flexible_request_in_month',
        'enable_show_hour_instead_of_day',
        'payslip_complaint',
        'enable_create_supplier_for_individual',
        'enable_timesheet_min_time_block',
        'enable_timesheet_shift_template_export',
        'enable_makeup_request_form',
        'enable_calculator_timesheet',
        'enable_multiple_shift',
        'enable_change_shift',
        'auto_create_makeup_request_form',
        'authorized_leave_woman_leave',
        'enable_edit_information',
        'template_export',
        'enable_basic_checkin_mobile',
        'enable_transportation_request',
        'enable_turn_off_leave_hours_mode',
        'sms_available',
        'enable_turn_off_leave_hours_mode',
        'enable_paid_leave_other',
        'enable_unpaid_leave_other',
        'enable_bussiness_request',
        'enable_bussiness_request_trip',
        'enable_bussiness_request_outside_working',
        'enable_bussiness_request_wfh',
        'enable_bussiness_request_other'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'approval_system_assigment_id' => 'object',
        'template_export' => 'array',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'template_export' => '{
            "timesheet": 1
        }'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function setFlexibleTimesheetSettingAttribute($setting)
    {
        $this->attributes['flexible_timesheet_setting'] = 0;
        if (!empty($setting)) {
            foreach ($setting as $key => $enable) {
                if ($enable && self::$FLEXIBLE_TIMESHEET_SETTING[$key]) {
                    $this->attributes['flexible_timesheet_setting'] += self::$FLEXIBLE_TIMESHEET_SETTING[$key];
                }
            }
        }
    }

    public function getFlexibleTimesheetSettingAttribute($setting)
    {
        $array = [];
        foreach (self::$FLEXIBLE_TIMESHEET_SETTING as $key => $permission) {
            $array[$key] = ConvertHelper::checkPermision($setting, $permission);
        }
        return $array;
    }

    public function getTimesheetDayBeginAttribute()
    {
        $value = $this->timesheet_day_begin_mark;
        $defaultValue = '00:00';
        // check if $value is a valid time HH:mm, otherwise return default value
        if (!preg_match('/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            return $defaultValue;
        }
        return $value;
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where('client_id', '=', $user->client_id);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
