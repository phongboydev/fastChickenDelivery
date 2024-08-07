<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class WebVersion extends Model
{
    use HasFactory;
    use UsesUuid, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */

    protected $keyType = 'string';

    public $timestamps = true;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */

    public $incrementing = false;
    protected $fillable = [
        'name',
        'notified_date',
        'is_active'
    ];

    protected $casts = [
        'notified_date' => 'date'
    ];

    public function webFeatureSliders(): HasMany
    {
        return $this->hasMany(WebFeatureSlider::class, 'web_version_id', 'id');
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
