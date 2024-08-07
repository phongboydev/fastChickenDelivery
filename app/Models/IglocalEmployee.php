<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code
 * @property string $name
 * @property string $role
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 * @property IglocalAssignment[] $iglocalAssignments
 */
class IglocalEmployee extends Model
{
    use Concerns\UsesUuid, SoftDeletes, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'iglocal_employees';

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
    protected $fillable = ['user_id', 'code', 'name', 'role', 'deleted_at', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    /**
     * @return HasMany
     */
    public function assignments()
    {
        return $this->hasMany('App\Models\IglocalAssignment');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @param Client|string $client
     * @return bool
     */
    public function isAssignedFor($client)
    {
        $clientId = null;
        if (is_string($client)) {
            $clientId = $client;
        } elseif ($client instanceof Client) {
            $clientId = $client->id;
        }
        if (!$clientId) {
            throw new InvalidArgumentException('Given $client parameter must be a string or instance of Client model');
        }
        return $this->assignments()->where("client_id", $clientId)->exists();
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        

        if (!$user->isInternalUser()) {
            switch ($role) {
                default:
                    return $query->whereNull($this->getTable() . '.id');
            }
        } else {

            return $query;

            // if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_iglocal_user')) {
            //     return $query;
            // }else{
            //     return $query->whereNull($this->getTable() . '.id');
            // }

        }
    }
}
