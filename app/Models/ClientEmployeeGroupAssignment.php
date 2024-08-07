<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\Constant;

class ClientEmployeeGroupAssignment extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_group_assignments';

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
    protected $fillable = ['client_id', 'client_employee_group_id', 'client_employee_id', 'approval'];

    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function clientEmployeeGroup()
    {
        return $this->belongsTo('App\Models\ClientEmployeeGroup');
    }

    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = auth()->user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            if ($role === Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeActiveEmployee($query)
    {
        $query = $query->whereHas('clientEmployee', function ($clientEmployee) {
            $clientEmployee->where('status', 'đang làm việc');
        });

        return $query;
    }
}
