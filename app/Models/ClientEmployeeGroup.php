<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\ClientEmployeeGroupAssignment;

class ClientEmployeeGroup extends Model
{
    use UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_groups';

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
    protected $fillable = ['client_id', 'name', 'created_at', 'updated_at'];

    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function clientEmployeeGroupAssignments()
    {
        return $this->hasMany(ClientEmployeeGroupAssignment::class);
    }

    public function clientEmployee()
    {
        return $this->belongsToMany(ClientEmployee::class, ClientEmployeeGroupAssignment::class, 'client_employee_group_id', 'client_employee_id');
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
