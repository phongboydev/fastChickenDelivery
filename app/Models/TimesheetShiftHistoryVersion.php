<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TimesheetShiftHistoryVersion extends Model
{
    use HasFactory, UsesUuid;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'timesheet_shift_history_version';

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
        'name',
        'group_name',
        'client_id',
        'sort_by'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('client', function (Builder $builder) {
            if (Auth::hasUser()) {
                $builder->where('client_id', '=', auth()->user()->client_id);
            }
        });
    }

    public function timesheetShiftHistory()
    {
        return $this->hasMany(TimesheetShiftHistory::class, 'version_group_id');
    }
}
