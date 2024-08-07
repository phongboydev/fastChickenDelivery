<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


/**
 * @property string $id
 * @property string $type_menu_tab
 * @property string $title
 * @property string $description
 * @property string $creator_id
 */
class LibraryQuestionAnswer extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use HasFactory, UsesUuid, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $table = 'library_question_answer';

    public $timestamps = true;
    /**
     * @var array
     */
    protected $fillable = [
        'type_menu_tab',
        'title',
        'description',
        'creator_id',
    ];
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    public function creator()
    {
        return $this->belongsTo('App\User', 'creator_id');
    }

    public function getMediaModel()
    {
        return $this->getMedia('LibraryQuestionAnswer');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("LibraryQuestionAnswer");
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
