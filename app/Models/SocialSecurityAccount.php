<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Models\Concerns\HasAssignment;
use Spatie\Activitylog\Traits\LogsActivity;
/**
 * @property string $id
 * @property string $client_id
 * @property string $username
 * @property string $password
 * @property string $created_at
 * @property string $updated_at
 */

class SocialSecurityAccount extends Model
{
    use UsesUuid, HasAssignment, LogsActivity;

    protected $table = 'social_security_accounts';

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
        'client_id',
        'username',
        'password',
        'creator_id',
        'state',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function getCreatorAttribute()
    {
        $user = User::select(['name', 'code'])->where('id', $this->creator_id)->first();
        
        if($user) {
            return "[{$user->code}] $user->name";
        }else{
            return '';
        }
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where("{$this->getTable()}.client_id", $user->client_id);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR) {
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
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
