<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string $contract_type
 * @property string $note
 * @property string $end_date
 * @property Client $client
 */
class DataImport extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'data_import';

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
        'creator_id',
        'type',
        'status',
        'approved_comment'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('DataImport');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();


        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
                default:
                    return $query->whereNull('id');
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
