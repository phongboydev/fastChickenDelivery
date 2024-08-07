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
 * @property string $approved_by
 * @property string $start_time
 * @property string $end_time
 * @property float $hours_register
 * @property string $type
 * @property string $reason
 * @property string $descriptions
 * @property string $status
 * @property string $approved_date
 * @property string $approved_comment
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property ClientEmployee $clientEmployee
 * @property ClientEmployee $approvedBy
 */
class ClientEmployeeOvertimeRequest extends Model
{
    use Concerns\UsesUuid, SoftDeletes, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_overtime_requests';

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
    protected $fillable = ['client_employee_id', 'approved_by', 'start_time', 'end_time', 'hours_register', 'type', 'reason', 'descriptions', 'status', 'approved_date', 'approved_comment', 'deleted_at', 'created_at', 'updated_at'];

    protected $dates = [
        'start_time',
        'end_time'
      ];

    /**
     * @return BelongsTo
     */
    public function approvedBy()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'approved_by');
    }

    /**
     * Get all of the post's comments.
     */
    public function approves()
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'client_employee_id');
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_LEADER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->belongToClientTo($user->clientEmployee);
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_STAFF:
                    $query->where(function($query) use ($user) {
                        $query->whereHas("approves", function ($query) {
                            return $query->authUserAccessible();
                        });
                        $query->orWhere('client_employee_id', '=', $user->clientEmployee->id);
                    });
                    logger("ClientEmployeeOvertimeRequest::scopeAuthUserAccessible Customer query");
                    logger($query->toSql());
                    return $query;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                    return $query->whereNull('id');
                case Constant::ROLE_INTERNAL_DIRECTOR:
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
