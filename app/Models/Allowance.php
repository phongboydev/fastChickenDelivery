<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Znck\Eloquent\Traits\BelongsToThrough;
/**
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string $allowance_group_id
 * @property string $allowance_value
 * @property Client $client
 */
class Allowance extends Model
{
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'allowances';

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
        'client_id',
        'allowance_group_id',
        'position',
        'name',
        'allowance_value'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsToThrough(Client::class, AllowanceGroup::class);
    }
}
