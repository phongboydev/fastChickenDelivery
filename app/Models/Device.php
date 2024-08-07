<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use UsesUuid;

    protected $table = 'devices';

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

    protected $fillable = [
        'user_id',
        'secret',
        'device_id',
        'device_name',
        'firebase_id',
        'category',
        'last_logged_in',
        'twofa_ts',
        'created_at',
        'updated_at',
        'is_verifed'
    ];

    protected $casts = [
        'last_logged_in' => 'datetime',
        'last_failed_at' => 'datetime',
        'should_notify' => 'boolean',
    ];
}
