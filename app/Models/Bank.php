<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class Bank extends Model
{
    use UsesUuid;
    protected $table = 'banks';

    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = ['province', 'bank_name', 'bank_id', 'province_id'];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
