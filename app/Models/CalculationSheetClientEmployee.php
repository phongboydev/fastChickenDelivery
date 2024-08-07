<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\HasPdfMedia;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $calculation_sheet_id
 * @property string $client_employee_id
 * @property float $calculated_value
 * @property string $is_disabled
 * @property CalculationSheet $calculationSheet
 * @property ClientEmployee $clientEmployee
 */
class CalculationSheetClientEmployee extends Model implements HasMedia
{

    use Concerns\UsesUuid, SoftDeletes, HasAssignment, BelongsToThrough;
    use HasPdfMedia, InteractsWithMedia;

    protected $table = 'calculation_sheet_client_employees';

    public $timestamps = true;

    const DELETED_AT = 'is_disabled';

    public function registerMediaCollections(): void
    {
        $this->registerPdfCollection();
    }

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
    protected $fillable = ['calculation_sheet_id', 'client_employee_id', 'calculated_value', 'is_disabled'];

    /**
     * @return BelongsTo
     */
    public function calculationSheet(): BelongsTo
    {
        return $this->belongsTo('App\Models\CalculationSheet');
    }

    public function workTimeRegisterLog(): HasMany
    {
        return $this->hasMany(WorkTimeRegisterLog::class, 'cal_sheet_client_employee_id');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee(): BelongsTo
    {
        return $this->belongsTo('App\Models\ClientEmployee')->withTrashed();
    }

    public function clientEmployeeGroupAssignment()
    {
        return $this->hasManyThrough(
            ClientEmployeeGroupAssignment::class,
            ClientEmployee::class
        );
    }

    public function client(): \Znck\Eloquent\Relations\BelongsToThrough
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    public function user(): \Znck\Eloquent\Relations\BelongsToThrough
    {
        return $this->belongsToThrough(User::class, ClientEmployee::class);
    }

    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            $clientId = $user->client_id;

            $normalPermissions = ["manage-payroll"];
            $advancedPermissions = ["advanced-manage-payroll-info", "advanced-manage-payroll-list"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, []);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow(), null, true)) {
                return $query->whereHas('calculationSheet', function (Builder $sub_query) use ($clientId) {
                    $sub_query->where('client_id', $clientId);
                });
            } else {
                return $query->where('client_employee_id', '=', $user->clientEmployee->id);
            }
        } else {

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

            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeListByUserLoginAccessible($query)
    {
        $user = Auth::user();
        return $query->where('client_employee_id', '=', $user->clientEmployee->id);
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

    public function scopeNotReady($query)
    {
        $query->where(function ($subQuery) {
            $subQuery->where("system_vars_ready", 0);
            $subQuery->orWhere("user_vars_ready", 0);
        });
    }

    public function scopeIsCompleted($query)
    {
        $query->where('completed', 1);
    }

    public function clientEmployeePayslipComplaint()
    {
        return $this->hasOne(ClientEmployeePayslipComplaint::class);
    }
}
