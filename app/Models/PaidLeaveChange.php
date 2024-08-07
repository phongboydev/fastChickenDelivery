<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class PayrollAccountantTemplate
 * @package App\Models
 */
class PaidLeaveChange extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'paid_leave_changes';

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
        'id',
        'client_id',
        'client_employee_id',
        'changed_ammount',
        'changed_reason',
        'changed_comment',
        'effective_at',
        'work_time_register_id',
        'category',
        'year_leave_type',
        'month',
        'year'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    /**
     * @return BelongsTo
     */
    public function workTimeRegister()
    {
        return $this->belongsTo('App\Models\WorktimeRegister');
    }

    public function workTimeRegisterPeriod()
    {
        return $this->hasMany('App\Models\WorkTimeRegisterPeriod', 'worktime_register_id', 'work_time_register_id');
    }

    public function scopeAuthUserAccessible(Builder $query): Builder
    {
        // Get User from token
        /** @var User $user */
        $query->whereHas("clientEmployee", function (Builder $clientEmployee) {
            /** @var $clientEmployee \App\Models\ClientEmployee */
            return $clientEmployee->authUserAccessible();
        });
        return $query;
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
