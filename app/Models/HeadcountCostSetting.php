<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $headcount_period_setting_id
 * @property integer $cost
 * @property integer $min_range
 * @property integer $max_range
 * @property string $created_at
 * @property string $updated_at
 */

class HeadcountCostSetting extends Model
{
    use HasFactory, UsesUuid, SoftDeletes, LogsActivity;

    protected $table = 'headcount_cost_settings';

    /**
     * @var array|string[]
     */
    protected static array $logAttributes = ['*'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'headcount_period_setting_id',
        'cost',
        'min_range',
        'max_range',
        'created_at',
        'updated_at',
        ];

    /**
     * @return BelongsTo
     */
    public function headcountPeriodSetting() : BelongsTo
    {
        return $this->belongsTo(HeadcountPeriodSetting::class, 'headcount_period_setting_id');
    }
}
