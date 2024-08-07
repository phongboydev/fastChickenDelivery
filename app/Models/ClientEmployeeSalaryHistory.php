<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Znck\Eloquent\Traits\BelongsToThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $readable_name
 * @property string $variable_name
 * @property float $variable_value
 * @property ClientEmployeeSalaryHistory $calculationSheet
 * @property ClientEmployee $clientEmployee
 */
class ClientEmployeeSalaryHistory extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment, BelongsToThrough, SoftDeletes;

    protected $table = 'client_employee_salary_histories';

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
        'client_employee_id',
        'start_date',
        'end_date',
        'type',
        'old_salary',
        'new_salary',
        'old_salary_for_social_insurance_payment',
        'new_salary_for_social_insurance_payment',
        'old_fixed_allowance',
        'new_fixed_allowance',
        'old_allowance_for_responsibilities',
        'new_allowance_for_responsibilities',
        'cron_job',
        'deleted_by_user_id'
    ];

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

    public function softDeleteWithUserId()
    {
        $this->deleted_by_user_id = Auth::user()->id;
        $this->deleted_at = Carbon::now();
        $this->save();
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if (!$user->isInternalUser()) {
             // Init advanced permission
            $advancedPermissions = [
                    'advanced-manage-payroll-list-read',
                    'advanced-manage-payroll-social-insurance-read'
                    ];

            // Init normal permission
            $normalPermissions = ['manage-social', 'manage-payroll', 'manage-employee-payroll'];
            // Check permission@há
            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                return $query;
            } else {
                return $query->where('client_employee_id', $user->clientEmployee->id);
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
