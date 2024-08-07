<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property ClientEmployee $clientEmployee
 */
class   ClientPayrollHeadCount extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_payroll_headcount';

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
    protected $fillable = ['client_id', 'month', 'year', 'total', 'created_at', 'updated_at'];

    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            return $query->where('client_id', null);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR) {
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
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
