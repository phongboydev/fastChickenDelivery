<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_id
 * @property string $shift_code
 * @property int $shift_type
 * @property string $type
 * @property string $check_in
 * @property string $check_out
 * @property string $break_start
 * @property string $break_end
 * @property string $created_at
 * @property string $updated_at
 * @property string $shift
 * @property float $hours
 * @property float $expected_core_hours
 * @property string $core_time_in
 * @property string $core_time_out
 * @property boolean $core_next_day
 * @property boolean $is_hidden
 * @property Client $client
 */
class TimesheetShift extends Model
{

    use UsesUuid, LogsActivity;

    const FLEXIBLE_SHIFT = 1;
    const CORE_SHIFT = 2;

    protected static $logAttributes = ['*'];

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
        'shift_type',
        'shift_code',
        'check_in',
        'check_out',
        'next_day',
        'break_start',
        'break_end',
        'next_day_break',
        'next_day_break_start',
        'is_hidden',
        'created_at',
        'updated_at',
        'color',
        'symbol',
        'acceptable_check_in',
        'core_time_in',
        'core_time_out',
        'core_next_day',
        'expected_core_hours',
        'hours',
        'shift'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('AppModels\Client');
    }

    public function timesheetShiftMapping()
    {
        return $this->hasMany(TimesheetShiftMapping::class, 'timesheet_shift_id');
    }

    public function getTotalDurationHoursAttribute()
    {
        $checkIn = Carbon::parse('2020-01-01  ' . $this->check_in);
        $checkOut = Carbon::parse('2020-01-01  ' . $this->check_out);

        if ($this->shift_next_day || $this->next_day) {
            $checkOut->addDay();
        }

        return round($checkOut->diffInMinutes($checkIn) / 60, 2);
    }

    public function getWorkHoursAttribute()
    {
        if ($this->shift_type == self::FLEXIBLE_SHIFT) {
            return $this->hours;
        }

        return $this->total_duration_hours - $this->rest_hours;
    }

    public function getRestHoursAttribute()
    {
        if (!$this->break_start || !$this->break_end) {
            return 0;
        }

        $breakIn = Carbon::parse('2020-01-01 ' . $this->break_start);
        if ($this->next_day_break_start) {
            $breakIn = $breakIn->addDay();
        }

        $breakOut = Carbon::parse('2020-01-01 ' . $this->break_end);
        if ($this->next_day_break) {
            $breakOut = $breakOut->addDay();
        }

        return round($breakOut->diffInMinutes($breakIn) / 60, 2);
    }

    public function scopeAuthUserAccessible($query) {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasPermissionTo('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
