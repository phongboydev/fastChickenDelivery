<?php

namespace App\Models;

use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_id
 * @property string $client_employee_id
 * @property string $location
 * @property string $datetime_checkin
 * @property string $longitude
 * @property string $latitude
 * @property string $note
 * @property string $created_at
 * @property string $updated_at
 */
class ClientEmployeeLocationHistory extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use Concerns\UsesUuid;

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
    protected $fillable = ['client_id', 'client_employee_id', 'location', 'datetime_checkin', 'longitude', 'latitude', 'note', 'created_at', 'updated_at'];

    public function getMediaModel() { return $this->getMedia('ClientEmployeeLocationHistory'); }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
              ->width(368)
              ->height(232)
              ->sharpen(10);
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function scopeAuthUserAccessible($query)
    {
        return true;
    }
}
