<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $id
 * @property string $client_id
 * @property string $code
 * @property string $name
 * @property string $phase_begin
 * @property string $phase_end
 * @property string $started_at
 * @property string $ended_at
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 */
class EvaluationGroup extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity;
    use SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'evaluation_group';

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
        'code',
        'name',
        'lock',
        'phase_begin',
        'phase_end',
        'configuration',
        'total_employee',
        'deadline_begin',
        'deadline_end',
        'created_by',
        'updated_by'
    ];

    public function evaluationObjects()
    {
        return $this->hasMany(EvaluationObject::class, 'evaluation_group_id', 'id' );
    }

    public function getMediaModel()
    {
        return $this->getMedia('EvaluationGroup');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("EvaluationGroup");
        $attachments = [];
        if (count($media) > 0) {
            foreach ($media as $key => $item) {
                $attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }

    public function evaluationSteps():HasMany{
        return $this->hasMany(EvaluationStep::class, 'evaluation_group_id', 'id');
    }

    public function scopeAuthUserAccessible($query)
    {
         // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $managerPermission = 'manage-evaluation';

        if (!$user->isInternalUser() && $user->hasDirectPermission($managerPermission)) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            return $query;
        }
    }
}
