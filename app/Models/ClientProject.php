<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $id
 * @property string $client_id
 * @property string $code
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property string $leader_client_employee_id
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property Client $client
 * @property ClientEmployee $client_employee
 */
class ClientProject extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'client_projects';

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
    protected $fillable = ['client_id', 'code', 'name', 'start_time', 'end_time', 'leader_client_employee_id', 'status', 'created_at', 'updated_at', 'deleted_at'];

    public function clientProjectTimelog(): HasMany
    {
        return $this->hasMany(ClientProjectTimelog::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientEmployee()
    {
        return $this->belongsToMany(ClientEmployee::class, 'client_project_employees');
    }

    public function clientProjectEmployee()
    {
        return $this->hasMany(ClientProjectEmployee::class)->whereHas('clientEmployee', function ($subQuery) {
            $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                ->whereNull('client_employees.deleted_at');
            $subQuery->orWhere(function ($subQueryLevelTwo) {
                $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                    ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'))
                    ->whereNull('client_employees.deleted_at');
            });
        });
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
