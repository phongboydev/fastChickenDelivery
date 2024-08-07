<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use UsesUuid;

    protected $table = 'error_logs';

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
        'client_id',
        'user_id',
        'exception_class',
        'message',
        'log_data',
        'app_info'
    ];
}
