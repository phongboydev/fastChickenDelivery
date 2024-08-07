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
 * @property string $client_employee_id
 * @property string $readable_name
 * @property string $variable_name
 * @property float $variable_value
 * @property string $created_at
 * @property string $updated_at
 * @property ClientEmployee $clientEmployee
 */
class ClientEmployeeCustomVariable extends Model
{
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_custom_variables';

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
    protected $fillable = ['client_employee_id', 'readable_name', 'variable_name', 'variable_value', 'created_at', 'updated_at'];

    protected $casts = [
        'variable_value' => 'float',
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

    /**
     * @param ClientEmployeeCustomVariable|Builder $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                    return $query->belongToClientTo($user->clientEmployee);
                default:
                    if ($user->hasAnyPermission(['manage-employee'])) {
                        return $query->belongToClientTo($user->clientEmployee);
                    }
                    return $query->whereNull('id');
            }
        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->belongToClientAssignedTo($user->iGlocalEmployee, "clientEmployee");
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
