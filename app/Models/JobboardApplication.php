<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
class JobboardApplication extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use UsesUuid, SoftDeletes, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'jobboard_applications';

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
        'jobboard_job_id',
        'appliant_name',
        'appliant_tel',
        'appliant_email',
        'cover_letter',
        'status',
        'deleted_at',
        'is_sent'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('JobboardApplication');
    }

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
    public function jobboardJob()
    {
        return $this->belongsTo('App\Models\JobboardJob');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }

    public function evaluations()
    {
        return $this->hasMany(JobboardApplicationEvaluation::class);
    }

    public function recruitmentProcesses()
    {
        return $this->hasManyThrough(RecruitmentProcess::class, JobboardJob::class);
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMediaModel();
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
                    'file_size' => $item->size,
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }
}
