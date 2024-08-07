<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


/**
 * @property string $id
 * @property string $title
 * @property string $description
 * @property string $order
 */
class Slider extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use HasFactory, UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */

    protected $keyType = 'string';

    public $timestamps = true;
    /**
     * @var array
     */
    protected $fillable = [
        'mobile_version_id',
        'title',
        'description',
        'order',
    ];
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public function getMediaModel()
    {
        return $this->getMedia('Slider');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function mobileVersion()
    {
        return $this->belongsTo('App\Models\MobileVersion', 'mobile_version_id');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("Slider");
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
    
    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
