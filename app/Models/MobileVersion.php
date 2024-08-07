<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * @property string $id
 * @property string $title
 * @property string $description
 * @property string $order
 */
class MobileVersion extends Model
{
    use HasFactory, UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */

    protected $keyType = 'string';

    public $timestamps = true;
    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'build',
        'is_active',
    ];
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }

    public function sliders(): HasMany
    {
        return $this->hasMany(Slider::class);
    }
}
