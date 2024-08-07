<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
/**
 * @property string $id
 * @property string $client_project_id
 * @property string $client_employee_id
 * @property string $work_hours
 * @property string $work_type
 * @property string $work_description
 * @property string $log_date
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property ClientEmployee $client_employee
 */
class ClientProjectTimelog extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'client_project_timelogs';

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
    protected $fillable = ['client_project_id', 'client_employee_id', 'work_hours', 'work_type', 'work_description', 'created_at', 'updated_at', 'log_date'];

    /**
     * @return BelongsTo
     */
    public function clientProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class);
    }

    public function clientEmployee (): BelongsTo
    {
        return $this->belongsTo(ClientEmployee::class);
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
