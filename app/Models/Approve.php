<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Krlove\EloquentModelGenerator\Model\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\User;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $type
 * @property string $content
 * @property string $creator_id
 * @property string $assignee_id
 * @property string $created_at
 * @property string $original_creator_id
 * @property string $approved_at
 * @property string $deleted_at
 */
class Approve extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'approves';

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
        'type',
        'content',
        'creator_id',
        'assignee_id',
        'created_at',
        'approved_at',
        'declined_at',
        'original_creator_id',
        'client_employee_group_id',
        'step',
        'target_type',
        'target_id',
        'is_final_step',
        'client_id',
        'approved_comment',
        'approve_group_id',
        'processing_state',
        'processing_error',
        'info_app',
        'source'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('Attachments');
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo('App\User', 'creator_id');
    }

    //TODO: Giai phap tinh the khi chua co luong moi cho don flexible
    public function getFlowTypeAttribute()
    {
        switch($this->type) {
            case "CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET":
                return "CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR";
            case "CLIENT_REQUEST_CHANGED_SHIFT":
                return "CLIENT_REQUEST_TIMESHEET_SHIFT";
            default:
                return $this->type;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function originalCreator()
    {
        return $this->belongsTo('App\User', 'original_creator_id');
        /*$user = Auth::user();
        if ($user && !$user->is_internal) {
            return $this->belongsTo('App\User', 'original_creator_id')->whereHas('clientEmployee', function ($subQuery) {
                $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                    ->whereNull('client_employees.deleted_at');
                $subQuery->orWhere(function ($subQueryLevelTwo) {
                    $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                        ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'))
                        ->whereNull('client_employees.deleted_at');
                });
            });
        } else {
            return  $this->belongsTo('App\User', 'original_creator_id');
        }*/
    }

    public function clientEmployeeTarget()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'target_id');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function timesheetTarget()
    {
        return $this->belongsTo('App\Models\Timesheet', 'target_id');
    }

    public function getClientEmployeeGroupIdAttribute()
    {
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_approval_flow')->where('client_id', $this->client_id)->first();
        if (
            $clientWorkflowSetting && $clientWorkflowSetting->advanced_permission_flow || $this->source === null
        ) {
            $originalCreator = User::with('clientEmployee')->find($this->original_creator_id);
            return self::getClientEmployeeGroupId($originalCreator, $this->flow_type ?? $this->type);
        } else {
            return $this->attributes['client_employee_group_id'];
        }
    }

    public static function getClientEmployeeGroupId(User $user, string $typeApprove)
    {

        if ($user && !$user->isInternalUser()) {

            $clientEmployeeGroupAssignments = $user->clientEmployee ? $user->clientEmployee->clientEmployeeGroupAssignment : [];

            return $clientEmployeeGroupAssignments && count($clientEmployeeGroupAssignments) == 1 && $typeApprove != 'CLIENT_REQUEST_PAYROLL' ? $clientEmployeeGroupAssignments[0]->client_employee_group_id : '0';
        }

        return 0;
    }

    /**
     * @return HasMany
     */
    public function assignee()
    {
        return $this->belongsTo('App\User', 'assignee_id');
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        // $user = Auth::user();
        // return $query->where('assignee_id', '=', $user->id)->orWhere('creator_id', '=', $user->id);
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserViewAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        $role = $user->getRole();

        if (!$user->isInternalUser() && !in_array($role, [Constant::ROLE_CLIENT_MANAGER])) {
            // TODO why role HR:: remove rolE==HR
            return $query->where('assignee_id', '=', $user->id)->orWhere('creator_id', '=', $user->id)->orWhere('original_creator_id', '=', $user->id);
        } else {
            return $query;
        }
    }

    /**
     * Get the owning commentable model.
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * WARNING: only using this function for relationships which have softDelete.
     */
    public function targetWithTrashed(): MorphTo
    {
        return $this->target()->withTrashed();
    }

    public function getStatusAdjustHoursAttribute()
    {
        $status = 'pending';

        if (!is_null($this->approved_at) && $this->is_final_step == 1) {
            $status = 'approved';
        } elseif (!is_null($this->declined_at)) {
            $status = 'declined';
        }

        return $status;
    }

    public function targetCalculationSheet()
    {
        return $this->belongsTo('App\Models\CalculationSheet', 'target_id');
    }

    public function targetWorktimeRegister()
    {
        return $this->belongsTo('App\Models\WorktimeRegister', 'target_id');
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        // 'content' => 'array',
    ];
}
