<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveCategory extends Model
{
    use UsesUuid, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leave_categories';

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
        'client_id',
        'type',
        'sub_type',
        'entitlement',
        'entitlement_next_year',
        'entitlement_next_year_effective_date',
        'cron_job',
        'start_date',
        'end_date',
        'year'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'name'
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientEmployeeLeaveManagementByMonth()
    {
        return $this->hasManyThrough(ClientEmployeeLeaveManagementByMonth::class, ClientEmployeeLeaveManagement::class);
    }

    public function clientEmployee()
    {
        return $this->hasManyThrough(ClientEmployee::class, ClientEmployeeLeaveManagement::class, 'leave_category_id', 'id', 'id', 'client_employee_id');
    }

    public function clientEmployeeLeaveManagement()
    {
        return $this->hasMany(ClientEmployeeLeaveManagement::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }

    public function getNameAttribute()
    {
        $key = "$this->type.$this->sub_type";
        if (array_key_exists($key, Constant::LEAVE_CATEGORY_TRANS)) {
            return __(Constant::LEAVE_CATEGORY_TRANS[$key]);
        } else {
            $category = WorktimeRegisterCategory::find($this->sub_type);
            return $category ? $category->category_name : null;
        }
    }
}
