<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasAssignment;

/**
 * @property string $id
 * @property string $client_id
 * @property string $batch_no
 * @property string $calculation_sheet_id
 * @property string $status
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 */

class SocialSecurityProfileHistory extends Model
{
    use UsesUuid, SoftDeletes, HasAssignment;

    protected $table = 'social_security_profile_histories';

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
        'profile_id',
        'comment',
        'old_status',
        'new_status',
        'salary_for_social_insurance_payment',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $normalPermissions = ["manage-social"];
            $advancedPermissions = ["advanced-manage-payroll"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-social-insurance-read"]);
            }
            $query->where("{$this->getTable()}.client_id", $user->client_id);
            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query;
            } else {
                return $query->whereNull("{$this->getTable()}.client_employee_id", $user->clientEmployee->id);
            }
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
