<?php

namespace App;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeGroupAssignment;
use App\Models\ClientWorkflowSetting;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Models\Device;
use App\Models\Evaluation;
use App\Models\EvaluationUser;
use App\Models\IglocalEmployee;
use App\Notifications\CustomResetPassword as CustomResetPassword;
use App\Support\Constant;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;

class User extends Authenticatable
{

    use HasFactory;

    protected $PERMISSIONS = [
        'VIEW_CALCULATIONSHEET',
        'export-payslip',
        'manage-payroll',
        'manage-debit-note',
        'manage-employee',
        'manage-project',
        'manage-social',
        'manage_iglocal_user',
        'manage_assignement',
        'manage_clients',
        'manage_formula',
        'manage_export_template',
        'manage_report_payroll',
        'manage-contract',
        'manage-timesheet',
        'manage-jobboard',
        'manage-workschedule',
        'manage-evaluation',
        'manage-training',
        'do-evaluation',
        'manage-wifi-checkin',
        'manage-location-checkin',
        'manage-camera-checkin',
        'permission_sample_payroll',
        'permission_apply_document',
        'manage-payment-request',
        'manage-payroll-complaint',
        'advanced-manage-employee',
        'advanced-manage-employee-list',
        'advanced-manage-employee-list-create',
        'advanced-manage-employee-list-read',
        'advanced-manage-employee-list-update',
        'advanced-manage-employee-list-delete',
        'advanced-manage-employee-list-import',
        'advanced-manage-employee-list-export',
        'advanced-manage-employee-group',
        'advanced-manage-employee-group-create',
        'advanced-manage-employee-group-read',
        'advanced-manage-employee-group-update',
        'advanced-manage-employee-group-delete',
        'advanced-manage-employee-history-position',
        'advanced-manage-employee-history-position-read',
        'advanced-manage-timesheet',
        'advanced-manage-timesheet-summary',
        'advanced-manage-timesheet-summary-read',
        'advanced-manage-timesheet-summary-export',
        'advanced-manage-timesheet-working',
        'advanced-manage-timesheet-working-read',
        'advanced-manage-timesheet-working-update',
        'advanced-manage-timesheet-working-import',
        'advanced-manage-timesheet-working-export',
        'advanced-manage-timesheet-adjust-hours',
        'advanced-manage-timesheet-adjust-hours-read',
        'advanced-manage-timesheet-adjust-hours-export',
        'advanced-manage-timesheet-leave',
        'advanced-manage-timesheet-leave-read',
        'advanced-manage-timesheet-leave-update',
        'advanced-manage-timesheet-leave-import',
        'advanced-manage-timesheet-leave-export',
        'advanced-manage-timesheet-overtime',
        'advanced-manage-timesheet-overtime-create',
        'advanced-manage-timesheet-overtime-read',
        'advanced-manage-timesheet-overtime-update',
        'advanced-manage-timesheet-overtime-export',
        'advanced-manage-timesheet-outside-working-wfh',
        'advanced-manage-timesheet-outside-working-wfh-read',
        'advanced-manage-timesheet-outside-working-wfh-update',
        'advanced-manage-timesheet-outside-working-wfh-export',
        'advanced-manage-timesheet-timesheet-shift',
        'advanced-manage-timesheet-timesheet-shift-create',
        'advanced-manage-timesheet-timesheet-shift-read',
        'advanced-manage-timesheet-timesheet-shift-update',
        'advanced-manage-timesheet-timesheet-shift-delete',
        'advanced-manage-timesheet-timesheet-shift-export',
        'advanced-manage-payroll',
        'advanced-manage-payroll-list',
        'advanced-manage-payroll-list-read',
        'advanced-manage-payroll-list-update',
        'advanced-manage-payroll-list-delete',
        'advanced-manage-payroll-list-export',
        'advanced-manage-payroll-info',
        'advanced-manage-payroll-info-read',
        'advanced-manage-payroll-social-insurance',
        'advanced-manage-payroll-social-insurance-read',
        'advanced-manage-payroll-social-insurance-update',
        'advanced-manage-payroll-social-insurance-delete',
        'advanced-manage-payroll-social-declaration',
        'advanced-manage-payroll-social-declaration-create',
        'advanced-manage-payroll-social-declaration-read',
        'advanced-manage-payroll-social-declaration-update',
        'advanced-manage-payroll-social-declaration-delete',
        'advanced-manage-payroll-salary-history',
        'advanced-manage-payroll-salary-history-create',
        'advanced-manage-payroll-salary-history-read',
        'advanced-manage-payroll-salary-history-update',
        'advanced-manage-payroll-salary-history-delete'
    ];

    use HasRoles, HasApiTokens, Notifiable, UsesUuid, LogsActivity, HasAssignment;

    /**
     * The attributes that store in activity properties.
     *
     * @var array
     */
    protected static $logAttributes = ['*'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'client_id',
        'username',
        'code',
        'is_internal',
        'prefered_language',
        'google2fa_enable',
        'google2fa_secret',
        'timezone_name',
        'is_email_notification',
        'is_active',
        'auto_approve',
        'is_2fa_email_enabled',
        'is_2fa_authenticator_enabled',
        'allow_call_api'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Ecrypt the user's google_2fa secret.
     *
     * @param string $value
     */
    public function setGoogle2faSecretAttribute($value)
    {
        $this->attributes['google2fa_secret'] = encrypt($value);
    }

    /**
     * Decrypt the user's google_2fa secret.
     *
     * @param string $value
     *
     * @return string
     */
    public function getGoogle2faSecretAttribute($value)
    {
        if (is_null($value)) {
            return false;
        }
        return decrypt($value);
    }

    /**
     * Override passport lookup
     *
     * @param $username
     *
     * @return \App\User
     */
    public function findForPassport($username): ?User
    {
        return $this->where('is_active', 1)
            ->where('username', $username)
            ->first();
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'id', 'client_id');
    }

    public function clientEmployee()
    {
        return $this->hasOne(ClientEmployee::class);
    }

    public function iGlocalEmployee()
    {
        return $this->hasOne(IglocalEmployee::class);
    }

    public function assignedApproves()
    {
        return $this->hasMany(Approve::class, "assignee_id")
            ->whereNull("approved_at")
            ->whereNull("declined_at");
        /*$user = Auth::user();
        $approve = $this->hasMany(Approve::class, "assignee_id")
            ->whereNull("approved_at")
            ->whereNull("declined_at");
        if ($user && !$user->is_internal) {
            $listId = $approve->groupBy('original_creator_id')->pluck('original_creator_id');
            $listUserId = ClientEmployee::whereIn('user_id', $listId)
                ->where(function ($subQuery) {
                    $subQuery->where('status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                        ->whereNull('deleted_at');
                    $subQuery->orWhere(function ($subQueryLevelTwo) {
                        $subQueryLevelTwo->where('status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                            ->where('quitted_at', '>', now()->format('Y-m-d H:i:s'))
                            ->whereNull('deleted_at');
                    });
                })->pluck('user_id');
            $approve = $approve->whereIn('original_creator_id', $listUserId);
        }

        return $approve;*/
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function devices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            Device::class,
            'user_id'
        );
    }

    public function getRole()
    {
        if ($this->is_internal) {
            if ($this->relationLoaded('iGlocalEmployee')) {
                $this->load('iGlocalEmployee');
            }
            $employee = $this->iGlocalEmployee;
        } else {
            if ($this->relationLoaded('clientEmployee')) {
                $this->load('clientEmployee');
            }
            $employee = $this->clientEmployee;
        }
        if ($employee) {
            return $employee->role;
        }
        logger()->warning("User::getRole orphan user data.", ['user_id' => $this->id]);
        return null;
    }

    public function isInternalUser()
    {
        return $this->is_internal === 1;
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {

            $normalPermissions = ["manage-employee"];
            $advancedPermissions = ["advanced-manage-employee-list", "advanced-manage-employee-list-read"];

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
            }

            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    return $query->belongToClientTo($user->clientEmployee);
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_STAFF:
                    return $query->whereNull('id');
            }
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_iglocal_user') || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee, "clientEmployee");
            }
        }
        return $query;
    }

    public function scopeSystemNotifiable(Builder $query)
    {
        return $query->where('is_internal', '=', 1);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    public function approveFlowUsers()
    {
        return $this->hasMany(ApproveFlowUser::class);
    }

    public function approveFlows()
    {
        return $this->belongsToMany(ApproveFlow::class, (new ApproveFlowUser)->getTable());
    }

    public function refreshPermissions()
    {
        $this->syncPermissions();

        $this->approveFlows->each(function (ApproveFlow $approve) {
            // permissionName must be either: permission_ or flow_, default flow_
            // exception defined in PERMISSION
            if (strpos(strtolower($approve->flow_name), "permission_") == 0 || strpos(strtolower($approve->flow_name), "flow_") == 0) {
                $permssionName = $approve->flow_name;
            } else {
                $permssionName = in_array($approve->flow_name, $this->PERMISSIONS) ?
                    // backward compatible
                    // TODO clean up this legacy permission name
                    $approve->flow_name :
                    // default
                    "flow_" . strtolower($approve->flow_name);
            }

            $this->forceGivePermissionTo($approve->group_id, $permssionName);
        });

        if ($this->is_internal) {
            $this->forceGivePermissionTo(0, 'is_internal');
            // Director mac dinh co quyen quan ly khach hang
            if ($this->iGlocalEmployee && $this->iGlocalEmployee->role == "director") {
                $this->forceGivePermissionTo(0, 'manage_permission', 'manage_clients', 'manage_export_template', 'manage_iglocal_user', 'manage_report_payroll', 'manage_settings',);
            }
        } else {
            $this->forceGivePermissionTo(0, 'is_client');
            if ($this->clientEmployee && $this->clientEmployee->role == "manager") {
                $this->forceGivePermissionTo(0, 'manage_permission');
            }
        }
    }

    public function getSelfEvaluationAttribute()
    {
        if (is_null($this->client) || is_null($this->clientEmployee())) {
            return false;
        }

        try {
            $evaluations = Evaluation::where("client_id", $this->client->id)
                ->where("client_employee_id", $this->clientEmployee->id)
                ->where("evaluator_list_id", "like", "%" . $this->clientEmployee->id . "#self%")
                ->get();


            if (count($evaluations) == 0) {
                return false;
            }

            $evaluation_ids = array_map(function ($item) {
                return $item['id'];
            }, $evaluations->toArray());


            $evaluation_users = EvaluationUser::where("client_id", $this->client->id)
                ->where("evaluator_id", $this->clientEmployee->id)
                ->whereIn("evaluation_id", $evaluation_ids)
                ->get();

            return count($evaluation_users) != count($evaluation_ids);
        } catch (Exception $e) {
            logger()->error("getSelfEvaluationAttribute");
            logger()->error($e->getMessage());
            return false;
        }
    }

    public function getOtherEvaluationAttribute()
    {
        if (is_null($this->client) || is_null($this->clientEmployee())) {
            return false;
        }

        try {
            $evaluations = Evaluation::where("client_id", $this->client->id)
                ->where("evaluator_list_id", "like", "%" . $this->clientEmployee->id . "#other%")
                ->get();

            if (count($evaluations) == 0) {
                return false;
            }

            $evaluation_ids = array_map(function ($item) {
                return $item['id'];
            }, $evaluations->toArray());


            $evaluation_users = EvaluationUser::where("client_id", $this->client->id)
                ->where("evaluator_id", $this->clientEmployee->id)
                ->whereIn("evaluation_id", $evaluation_ids)
                ->get();

            return count($evaluation_users) != count($evaluation_ids);
        } catch (Exception $e) {
            logger()->error("getOtherEvaluationAttribute");
            logger()->error($e->getMessage());
            return false;
        }
    }

    public function getShortUsernameAttribute()
    {
        return str_replace($this->client_id . "_", "", $this->username);
    }

    public static function findByVPOCredentials(
        string $username,
        string $clientCode = "",
        bool $isInternal = false
    ): ?User {
        if (!$isInternal) {
            $client = Client::query()->where('code', $clientCode)->first();
            if (empty($client)) {
                return null;
            }
            $realUsername = sprintf("%s_%s", $client->id, $username);
            return User::query()
                ->where("client_id", $client->id)
                ->where('username', '=', $realUsername)
                ->first();
        }
        $realUsername = sprintf("%s_%s", Constant::INTERNAL_DUMMY_CLIENT_ID, $username);
        return User::query()
            ->where("client_id", Constant::INTERNAL_DUMMY_CLIENT_ID)
            ->where('username', '=', $realUsername)
            ->first();
    }

    public function forceGivePermissionTo($groupId = 0, ...$permissions)
    {
        Artisan::call('permission:cache-reset');

        $modifiedPermissions = collect($permissions)->map(function ($permission) use ($groupId) {
            if (Str::isUuid($groupId) && Str::of($permission)->startsWith('advanced-manage-')) {
                return $permission . '_' . $groupId;
            }
            return $permission;
        });

        $modifiedPermissions->each(function ($p) {
            if (!Permission::where(['name' => $p, 'guard_name' => 'api'])->exists()) {
                $permission = Permission::make(['name' => $p, 'guard_name' => 'api']);
                $permission->saveOrFail();
            }
        });

        $this->givePermissionTo($modifiedPermissions);
    }

    public function forceAdvanceGivePermissionTo($revoke = false, $permissions, $groupId)
    {
        if (Str::isUuid($groupId)) {
            $permissions = $permissions . '_' . $groupId;
        }

        $hasPermission = Permission::getPermissions(['name' => $permissions, 'guard_name' => 'api'])->first();
        if (!$hasPermission) {
            Artisan::call('permission:cache-reset');
            $permission = Permission::make(['name' => $permissions, 'guard_name' => 'api']);
            $permission->saveOrFail();
        }

        if (!$this->hasPermissionTo($permissions) && !$revoke) {
            $this->givePermissionTo($permissions);
        } elseif ($this->hasPermissionTo($permissions) && $revoke) {
            if ($this->hasPermissionTo($permissions)) {
                $this->revokePermissionTo($permissions);
            }
        }
    }

    public function checkHavePermission($normalPermissions, $advancedPermissions, $isAdvanced = false, $clientId = null, $hasAllPermission = false)
    {
        $isHavePermission = false;
        // Customer
        if (!$this->isInternalUser()) {
            // Check permission to override isAdvanced because advanced permission only support(list client employee, work and leave, salary and assurance)
            if ($isAdvanced) {
                $isAdvanced = false;
                foreach ($advancedPermissions as $permission) {
                    if (Str::of($permission)->startsWith('advanced-manage-')) {
                        $isAdvanced = true;
                        break;
                    }
                }
            }

            // Use check advanced permission
            if ($isAdvanced) {
                $clientEmployeeGroupAssignment = $this->clientEmployee['clientEmployeeGroupAssignment'];
                $groupIds = $clientEmployeeGroupAssignment->isNotEmpty()
                    ? $clientEmployeeGroupAssignment->pluck('client_employee_group_id')->all()
                    : ['0'];

                $areGroupIdsValidUuids = collect($groupIds)->every(function ($groupId) {
                    return Str::isUuid($groupId);
                });

                $approveFlow = ApproveFlow::whereIn('flow_name', $advancedPermissions)
                    ->where('client_id', $this->client_id)
                    ->whereIn('group_id', $groupIds)
                    ->whereHas('approveFlowUsers', function ($query) use ($groupIds) {
                        $query->where('user_id', $this->id);
                        $query->whereIn('group_id', $groupIds);
                    })->first();

                if ($areGroupIdsValidUuids) {
                    $advancedPermissions = collect($advancedPermissions)->map(function ($permission) use ($groupIds) {
                        if (Str::of($permission)->startsWith('advanced-manage-')) {
                            foreach ($groupIds as $groupId) {
                                $permissions[] = $permission . '_' . $groupId;
                            }
                            return $permissions;
                        }
                    })->flatten();
                }

                if ($approveFlow && $this->hasAnyPermission($advancedPermissions)) {
                    $isHavePermission = true;
                }
                // Use normal permission
            } else {
                if ($hasAllPermission) {
                    $isHavePermission = $this->hasAllPermissions($normalPermissions);
                } else {
                    $isHavePermission = $this->hasAnyPermission($normalPermissions);
                }
            }
            // Internal
        } else {
            $role = $this->getRole();
            if (
                $role == Constant::ROLE_INTERNAL_DIRECTOR
                || $this->hasDirectPermission('manage_iglocal_user')
                || $this->hasDirectPermission('manage_clients')
                || (!is_null($clientId) && $this->iGlocalEmployee->isAssignedFor($clientId))
            ) {
                $isHavePermission = true;
            }
        }

        return $isHavePermission;
    }

    public function getSettingAdvancedPermissionFlow($clientId = null)
    {
        if (!$clientId) {
            $clientId = $this->client_id;
        }
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $clientId)->first();

        return !empty($clientWorkflowSetting->advanced_permission_flow);
    }

    public function getListClientEmployeeByGroupIds($user, $paramGroupIds)
    {
        $clientEmployee = $user->clientEmployee;
        $isAdvancedPermissionFlow = $user->getSettingAdvancedPermissionFlow($clientEmployee->client_id);
        $listClientEmployeeByGroup = [];
        if ($isAdvancedPermissionFlow && !empty($paramGroupIds)) {
            $listGroupByClientId = [];
            $clientEmployeeId = $clientEmployee->id;
            if ($clientEmployeeId) {
                $listGroupByClientId = ClientEmployeeGroupAssignment::where('client_employee_id', $clientEmployeeId)->get()->pluck('client_employee_group_id');
            }
            $paramListGroupIdFinal = [];
            foreach ($paramGroupIds as $itemParam) {
                foreach ($listGroupByClientId as $itemDB) {
                    if ($itemDB == $itemParam) {
                        $paramListGroupIdFinal[] = $itemParam;
                        break;
                    }
                }
            }

            if (!empty($paramListGroupIdFinal)) {
                $listClientEmployeeByGroup =  ClientEmployeeGroupAssignment::whereIn('client_employee_group_id', $paramListGroupIdFinal)->get()->pluck('client_employee_id');
            }
        }
        return $listClientEmployeeByGroup;
    }

    /**
     * @param $title string
     * @param $body string
     * @param $data array extra data if needed
     *
     * @return void
     * @throws \Kreait\Firebase\Exception\FirebaseException
     * @throws \Kreait\Firebase\Exception\MessagingException
     */
    public function pushDeviceNotification(string $title, string $body, array $data = []): void
    {
        $failedDeviceIds = [];
        $hasError = false;

        $this
            ->devices()
            ->where('should_notify', true)
            ->each(function (Device $device) use ($title, $body, $data, &$hasError, &$failedDeviceIds) {
                $message = CloudMessage::fromArray([
                    'token' => $device->firebase_id,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $data,
                ]);
                try {
                    Firebase::messaging()->send($message);
                } catch (Exception $e) {
                    $failedDeviceIds[] = $device->device_id;
                    $hasError = true;
                    // stop sending notification to this device
                    $device->last_failed_message = $e->getMessage();
                    $device->last_failed_at = now();
                    $device->should_notify = false;
                    $device->save();
                    logger("Device [" . $device->device_id . "] failed: " . $e->getMessage());
                }
            });

        if ($hasError) {
            logger("Push notification failed for some devices", ['device_ids' => $failedDeviceIds]);
        }
    }

    /**
     * Load this user's prefered language to the app.
     * @return void
     */
    public function loadUserLocale(): void
    {
        App::setLocale($this->prefered_language);
    }
}
