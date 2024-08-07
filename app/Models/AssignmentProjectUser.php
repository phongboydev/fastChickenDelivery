<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
/**
 * @property string $id
 * @property string $assignment_project_id
 * @property string $user_id
 * @property string $inviter_user_id
 * @property string $access_level
 * @property string $created_at
 * @property string $updated_at
 * @property AssignmentProject $assignment_project
 */
class AssignmentProjectUser extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'assignment_project_users';

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
    protected $fillable = ['assignment_project_id', 'user_id', 'inviter_user_id', 'access_level', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function assignment_project()
    {
        return $this->belongsTo('App\Models\AssignmentProject');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function inviter()
    {
        return $this->belongsTo('App\User', 'inviter_user_id');
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
