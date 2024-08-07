<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_id
 * @property string $code
 * @property string $description
 * @property string $attendance
 * @property Client $client
 */
class TrainingSeminar extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'training_seminars';

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
        'client_id',
        'code',
        'description',
        'attendance'
    ];

    public function getClientDepartmentsAttribute()
    {
        return ClientDepartmentTrainingSeminar::where('training_seminar_id', $this->id)->get();
    }

    public function getClientDepartmentPostionsAttribute()
    {
        return ClientDepartmentPositionTrainingSeminar::where('training_seminar_id', $this->id)->get();
    }

    public function getScheduleAttribute()
    {
        return TrainingSeminarSchedule::where('training_seminar_id', $this->id)->get();
    }

    public function getMediaModel()
    {
        return $this->getMedia('TrainingSeminar');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            return $query;
        }
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientEmployee()
    {
        return $this->hasMany(ClientEmployeeTrainingSeminar::class)->whereHas('clientEmployee', function ($subQuery) {
            $subQuery->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_WORKING)
                ->whereNull('client_employees.deleted_at');
            $subQuery->orWhere(function ($subQueryLevelTwo) {
                $subQueryLevelTwo->where('client_employees.status', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                    ->where('client_employees.quitted_at', '>', now()->format('Y-m-d H:i:s'))
                    ->whereNull('client_employees.deleted_at');
            });
        });
    }

    public function trainingSeminarSchedule()
    {
        return $this->hasMany(TrainingSeminarSchedule::class);
    }

    public function clientDepartmentPositionTrainingSeminar()
    {
        return $this->hasMany(ClientDepartmentPositionTrainingSeminar::class);
    }

    public function clientDepartmentTrainingSeminar()
    {
        return $this->hasMany(ClientDepartmentTrainingSeminar::class);
    }

    public function trainingSeminarAttendance()
    {
        return $this->hasManyThrough(
            TrainingSeminarAttendance::class,
            TrainingSeminarSchedule::class,
            'training_seminar_id',
            'training_seminar_schedule_id',
            'id',
            'id'
        );
    }

    public function teachers()
    {
        return $this->hasMany(ClientTeacherTrainingSeminar::class);
    }
}
