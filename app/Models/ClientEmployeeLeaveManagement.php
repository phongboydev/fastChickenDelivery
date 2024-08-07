<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\UsesUuid;
use Znck\Eloquent\Traits\BelongsToThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientEmployeeLeaveManagement extends Model
{
    use UsesUuid, LogsActivity, BelongsToThrough, SoftDeletes;

    protected static $logAttributes = ['*'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'client_employee_leave_management';

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
        'leave_category_id',
        'client_employee_id',
        'entitlement',
        'remaining_entitlement',
        'entitlement_used',
        'entitlement_last_year',
        'remaining_entitlement_last_year',
        'used_entitlement_last_year',
        'entitlement_last_year_effective_date',
        'start_date',
        'end_date',
        'year'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'entitlement' => 0,
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployee::class);
    }

    /**
     * @return BelongsTo
     */
    public function leaveCategory()
    {
        return $this->belongsTo(LeaveCategory::class);
    }

    /**
     * @return HasMany
     */
    public function clientEmployeeLeaveManagementByMonth()
    {
        return $this->hasMany(ClientEmployeeLeaveManagementByMonth::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
