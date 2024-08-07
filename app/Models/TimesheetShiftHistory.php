<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $timesheet_id
 * @property string $timesheet_shift_id
 * @property int $type
 * @property string $updated_by
 * @property string $created_at
 * @property string $updated_at
 */
class TimesheetShiftHistory extends Model
{
    use HasFactory, UsesUuid;

    const WORKING = 0;
    const IS_OFF_DAY = 1;
    const IS_HOLIDAY = 2;
    const IS_EMPTY_SHIFT = 3;

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
        'id',
        'timesheet_id',
        'timesheet_shift_id',
        'type',
        'updated_by',
        'created_at',
        'updated_at'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function timesheetShiftHistoryVersion()
    {
        return $this->belongsTo(TimesheetShiftHistoryVersion::class, 'version_group_id');
    }
}
