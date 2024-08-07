<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $client_id
 * @property string $spot_name
 * @property string $spot_ssid
 * @property string $spot_mac
 * @property string $memo
 * @property string $longitude
 * @property string $latitude
 * @property string $radius
 * @property string $address
 * @property string $created_at
 * @property string $updated_at
 */
class ClientWifiCheckinSpot extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'client_wifi_checkin_spots';

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
    protected $fillable = ['client_id', 'spot_name', 'spot_ssid', 'spot_mac', 'memo', 'longitude', 'latitude', 'radius', 'address', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query)
    {
        $user = auth()->user();
        $clientId = $user->client_id;
        return $query->where('client_id', $clientId);
    }
}
