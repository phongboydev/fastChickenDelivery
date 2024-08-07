<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeCategory extends Model
{
    use UsesUuid, LogsActivity, SoftDeletes;
    protected static $logAttributes = ['*'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'overtime_categories';

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
    protected $fillable = [
        'client_id',
        'entitlement_month',
        'entitlement_year',
        'start_date',
        'end_date',
        'year',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
