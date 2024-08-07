<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_id
 * @property int $step
 * @property string $flow_name
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property ApproveFlowsUser[] $approveFlowsUsers
 */
class ApproveFlow extends Model
{

    use UsesUuid, HasAssignment, LogsActivity;

    protected static $logAttributes = ['*'];

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
    protected $fillable = ['client_id', 'step', 'flow_name', 'level', 'group_id', 'created_at', 'updated_at'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function approveFlowUsers()
    {
        return $this->hasMany('App\Models\ApproveFlowUser');
    }

    /**
     * @param $query
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
                case Constant::ROLE_CLIENT_STAFF:
                case Constant::ROLE_CLIENT_LEADER:
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            return $query;
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
