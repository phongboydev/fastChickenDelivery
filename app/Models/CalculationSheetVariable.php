<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $calculation_sheet_id
 * @property string $client_employee_id
 * @property string $readable_name
 * @property string $variable_name
 * @property float $variable_value
 * @property CalculationSheet $calculationSheet
 * @property ClientEmployee $clientEmployee
 */
class CalculationSheetVariable extends Model
{
    use Concerns\UsesUuid, HasAssignment, BelongsToThrough;

    protected $table = 'calculation_sheet_variables';

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
    protected $fillable = ['calculation_sheet_id', 'client_employee_id', 'readable_name', 'variable_name', 'variable_value'];

    // protected $casts = [
    //     'variable_value' => 'float',
    // ];

    /**
     * @return BelongsTo
     */
    public function calculationSheet()
    {
        return $this->belongsTo('App\Models\CalculationSheet');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        $user = Auth::user();

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
                return $query->whereNull('id');
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    return $query->belongToClientAssignedTo($user->iGlocalEmployee, "clientEmployee");

                case Constant::ROLE_INTERNAL_DIRECTOR:
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                    return $query;
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
