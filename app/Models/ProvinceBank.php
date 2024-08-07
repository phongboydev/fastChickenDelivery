<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $client_id
 * @property string $readable_name
 * @property string $variable_name
 * @property string $scope
 * @property float $variable_value
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 */
class ProvinceBank extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'province_banks';

    protected $guarded = [];

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
    protected $fillable = ['id', 'province_id', 'bank_code', 'bank_name', 'created_at', 'updated_at'];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }

    public function province()
    {
        return $this->belongsTo('App\Models\Province');
    }
}
