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
class Province extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'provinces';

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
    protected $fillable = ['id', 'province_code', 'province_name', 'created_at', 'updated_at'];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
