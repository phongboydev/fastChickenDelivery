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
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property string $assignment_project_id
 * @property string $creator_user_id
 * @property string $assignee_user_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property AssignmentProject $assignmentProject
 */
class AssignmentTask extends Model implements HasMedia
{
    use UsesUuid, LogsActivity, HasAssignment;
    use InteractsWithMedia;
    use MediaTrait;

    protected static $logAttributes = ['*'];

    protected $table = 'assignment_tasks';

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
        'code',
        'name',
        'assignment_project_id',
        'creator_user_id',
        'assignee_user_id',
        'status',
        'created_at',
        'updated_at',
        'deleted_at',
        'desc',
        'start_date',
        'end_date'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('AssignmentTask');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }

    /**
     * @return BelongsTo
     */
    public function assignmentProject()
    {
        return $this->belongsTo('App\Models\AssignmentProject');
    }

    public function assignee()
    {
        return $this->belongsTo('App\User', 'assignee_user_id');
    }

    public function creator()
    {
        return $this->belongsTo('App\User', 'creator_user_id');
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

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
