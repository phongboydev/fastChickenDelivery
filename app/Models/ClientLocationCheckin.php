<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $client_id
 * @property string $location_checkin
 * @property string $address
 * @property string $longitude
 * @property string $latitude
 * @property string $radius
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 */
class ClientLocationCheckin extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'client_location_checkin';

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
    protected $fillable = ['client_id', 'location_checkin', 'address', 'longitude', 'latitude', 'radius', 'status', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query)
    {
        return true;
    }
}
