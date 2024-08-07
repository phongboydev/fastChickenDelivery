<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Class WorkSchedule
 * @package App\Models
 * @property Carbon schedule_date
 */
class WorkScheduleGroupTemplate extends Model
{
    use UsesUuid, HasAssignment;
    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'work_days',
        'name',
        'check_in',
        'check_out',
        'core_time_in',
        'core_time_out',
        'start_break',
        'end_break',
        'rest_hours',
        'is_default',
        'period_start_date',
        'period_end_month',
        'period_end_date',
        'enable_makeup_or_ot_form'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function workSchedules()
    {
        return $this->hasManyThrough(WorkSchedule::class, WorkScheduleGroup::class);
    }

    public function workScheduleGroup()
    {
        return $this->hasMany(WorkScheduleGroup::class);
    }

    /**
     * @param self $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where('client_id', '=', $user->client_id);
        } else {

            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
