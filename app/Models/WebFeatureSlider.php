<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\InteractsWithMedia;

class WebFeatureSlider extends Model implements HasMedia
{
    use HasFactory;
    use UsesUuid, LogsActivity, SoftDeletes;
    use MediaTrait, InteractsWithMedia;

    protected static $logAttributes = ['*'];
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */

    protected $keyType = 'string';

    public $timestamps = true;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */

    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'order',
        'web_version_id'
    ];

    public function webVersion(): BelongsTo
    {
        return $this->belongsTo(WebVersion::class, 'web_version_id', 'id');
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
    public function getMediaModel()
    {
        return $this->getMedia('WebFeatureSlider');
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
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10);
    }
}
