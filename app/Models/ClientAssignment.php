<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Client;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $leader_id
 * @property string $staff_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $client_id
 * @property ClientEmployee $staff
 * @property ClientEmployee $leader
 */
class ClientAssignment extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

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
    protected $fillable = ['leader_id', 'staff_id', 'client_id', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function leader()
    {
        return $this->belongsTo(ClientEmployee::class, 'leader_id');
    }

    /**
     * @return BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(ClientEmployee::class, 'staff_id');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }


    /**
     * @property Builder $query
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    return $query->belongToClientAssignedTo($user->iGlocalEmployee);
                case Constant::ROLE_INTERNAL_DIRECTOR:
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                    return $query;
            }
        }
    }
}
