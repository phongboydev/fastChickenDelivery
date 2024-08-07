<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;


class HanetPlace extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'hanet_places';

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

    public function getMediaModel() { return $this->getMedia('HanetPlace'); }

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'name',
        'address',
        'hanet_place_id'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function hanetPersons()
    {
        return $this->hasMany('App\Models\HanetPlacePerson');
    }


    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_STAFF:
                case Constant::ROLE_CLIENT_LEADER:
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
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
