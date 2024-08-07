<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
/**
 * @property string $id
 * @property string $client_id
 * @property boolean $custom_domain_enabled
 * @property string $domain_mapping
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 */
class HanetSetting extends Model
{
    use UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'hanet_settings';

    public $timestamps = false;

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
        'partner_token',
        'token',
        'expiration_date',
        'status'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }
}