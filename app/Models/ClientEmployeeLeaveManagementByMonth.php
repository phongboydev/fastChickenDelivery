<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientEmployeeLeaveManagementByMonth extends Model
{
    use UsesUuid, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'client_employee_leave_management_by_months';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = [
        'client_employee_leave_management_id',
        'name',
        'entitlement_used',
        'remaining_entitlement',
        'remaining_entitlement_last_year',
        'used_entitlement_last_year',
        'start_date',
        'end_date',
        'year',
    ];


    public function clientEmployeeLeaveManagement()
    {
        return $this->belongsTo(ClientEmployeeLeaveManagement::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
