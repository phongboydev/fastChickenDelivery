<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Nationality extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'nationalities';

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
    protected $fillable = ['id', 'nationality_id', 'nationality_code', 'nationality_name', 'created_at', 'updated_at'];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
