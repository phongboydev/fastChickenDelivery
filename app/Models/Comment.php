<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
/**
 * @property string $id
 * @property string $type
 * @property string $content
 * @property string $user_id
 * @property string $assignee_id
 * @property string $created_at
 * @property string $approved_at
 * @property string $deleted_at
 */
class Comment extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use UsesUuid, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'comments';

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
        'user_id',
        'message',
        'created_at',
        'deleted_at',
        'target_type',
        'target_id'
    ];

    public function getMediaModel() { return $this->getMedia('Comment'); }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
              ->width(50)
              ->height(50)
              ->sharpen(10);
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * Get the owning commentable model.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeAuthUserAccessible(Builder $query)
    {
        return true;
    }

}
