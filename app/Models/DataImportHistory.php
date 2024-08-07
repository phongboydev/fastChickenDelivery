<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_id
 * @property string $jobboard_job_id
 * @property string $appliant_name
 * @property string $appliant_tel
 * @property string $appliant_email
 * @property string $cover_letter
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property Client $client
 */
class DataImportHistory extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'import_data_histories';

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
        'user_id',
        'type',
        'created_at', 'updated_at'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('DataImportHistory');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
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
