<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $approve_flow_id
 * @property string $user_id
 * @property string $client_id
 * @property string $created_at
 * @property string $updated_at
 * @property ApproveFlow $approveFlow
 * @property User $user
 */
class ApproveFlowUser extends Model
{
    use \Awobaz\Compoships\Compoships;
    use UsesUuid, HasAssignment, BelongsToThrough, LogsActivity;

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
    protected $fillable = [
        'approve_flow_id', 'user_id', 'parent_id', 'group_id'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approveFlow()
    {
        return $this->belongsTo(ApproveFlow::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, ApproveFlowUser::class);
    }

    public function hasParent()
    {
        return $this->parent();
    }

    public function hasChild()
    {
        return $this->children();
    }

    public function parent()
    {
        return $this->belongsTo(ApproveFlowUser::class, ['parent_id', 'approve_flow_id', 'group_id'], ['user_id', 'approve_flow_id', 'group_id'])->with('parent');
    }

    public function children()
    {
        return $this->hasMany(ApproveFlowUser::class, ['parent_id', 'approve_flow_id', 'group_id'], ['user_id', 'approve_flow_id', 'group_id'])->with('children');
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
                    // return $query->where('client_id', '=', $user->client_id);
                    return $query->whereNull('id');
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
