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

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $salary
 * @property string $title
 * @property string $position
 * @property ClientEmployeeSalary $calculationSheet
 * @property ClientEmployee $clientEmployee
 */
class ClientEmployeeSalary extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected $table = 'client_employee_salaries';

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
    protected $fillable = ['client_id', 'client_employee_id', 'created_at', 'salary', 'title', 'department', 'position'];

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->belongToClientTo($user->clientEmployee);
                case Constant::ROLE_CLIENT_STAFF:
                case Constant::ROLE_CLIENT_LEADER:
                default:
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
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
