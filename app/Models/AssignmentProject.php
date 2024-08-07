<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $client_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property Clients $clients
 */
class AssignmentProject extends Model
{
    use UsesUuid, LogsActivity, HasAssignment, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'assignment_projects';

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
    protected $fillable = ['code', 'name', 'client_id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
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

     /**
     * Get all of the assignmentTasks for the AssignmentProject
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assignmentTasks()
    {
        return $this->hasMany(AssignmentTask::class, 'assignment_project_id', 'id');
    }

    /**
     * Get all of the assignee for the AssignmentProject
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assignee()
    {
        return $this->hasMany(AssignmentProjectUser::class, 'assignment_project_id', 'id');
    }

    public static function createProjectForClient(Client $client)
    {
        $projData = [
            'code' => $client->code,
            'name' => $client->company_name,
            'client_id' => $client->id,
        ];
        /** @var \App\Models\AssignmentProject $project */
        $project = $client->assignmentProject()->create($projData);
        $iglocalEmployees = $client->assignedInternalEmployees;
        foreach ($iglocalEmployees as $employee) {
            $accessLevel = $employee->role == 'leader' ? 'leader' : ($employee->role == 'director' ? 'manager' : 'member');
            $assignee = [
                'assignment_project_id' => $project->id,
                'user_id' => $employee->user_id,
                'access_level' => $accessLevel,
                'inviter_user_id' => null,
            ];
            $project->assignee()->create($assignee);
        }
    }
}
