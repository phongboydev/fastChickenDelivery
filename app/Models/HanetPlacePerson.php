<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;


class HanetPlacePerson extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'hanet_place_persons';

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
    protected $fillable = [
        'hanet_person_id',
        'person_id',
        'hanet_place_id',
        'created_at', 
        'updated_at'
    ];

    /**
     * @return BelongsTo
     */

    public function hanetPerson()
    {
        return $this->belongsTo('App\Models\HanetPerson');
    }

    public function hanetPlace()
    {
        return $this->belongsTo('App\Models\HanetPlace');
    }

}
