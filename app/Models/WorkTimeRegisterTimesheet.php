<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTimeRegisterTimesheet extends Model
{
    use UsesUuid, SoftDeletes, LogsActivity, HasFactory;

    public const OT_TYPE = 1;
    public const OT_MIDNIGHT_TYPE = 2;
    public const OT_MAKEUP_HOURS_TYPE = 3;
    public const TYPE_TEXT = [
        1 => "overtime_hours",
        2 => "midnight_overtime_hours",
        3 => "makeup_hours"
    ];
    protected static $logAttributes = ['*'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $table = 'work_time_register_timesheets';

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'timesheet_id',
        'register_id',
        'client_employee_id',
        'type',
        'time_values',
        'month_lock',
        'year_lock',
        'deleted_at'
    ];

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class, 'timesheet_id');
    }


    public function workTimeRegister()
    {
        return $this->belongsTo(WorktimeRegister::class, 'register_id');
    }

    public function workTimeRegisterLog(): HasMany
    {
        return $this->hasMany(WorkTimeRegisterLog::class, 'work_time_register_timesheet_id');
    }
}
