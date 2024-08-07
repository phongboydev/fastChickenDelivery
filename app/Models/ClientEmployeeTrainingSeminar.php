<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $client_id
 * @property string $client_employee_id
 * @property string $training_seminar_id
 * @property string $start_date
 * @property string $end_date
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property ClientEmployee $client_employee
 * @property TrainingSeminar $training_seminar
 */
class ClientEmployeeTrainingSeminar extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment;
    use \Znck\Eloquent\Traits\BelongsToThrough;
    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_training_seminars';

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
        'client_employee_id',
        'training_seminar_id'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'state', 'client_employee_attendance_count', 'training_seminar_attendance_count'
    ];

    public function getStateAttribute()
    {
        if ($this->client_employee_attendance_count >= $this->training_seminar_attendance_count) {
            return 'completed';
        } elseif ($this->client_employee_attendance_count <= $this->training_seminar_attendance_count && $this->client_employee_attendance_count !== 0) {
            return 'joined';
        } else {
            return 'not_yet_joined';
        }
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

    public function getClientEmployeeAttendanceCountAttribute()
    {
        return $this->clientEmployeeTrainingSeminarAttendance()->count();
    }

    public function getTrainingSeminarAttendanceCountAttribute()
    {
        return $this->trainingSeminar()->value('attendance');
    }

    public function getMediaModel()
    {
        return $this->getMedia('ClientEmployeeTrainingSeminar');
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
        return $this->belongsTo(ClientEmployee::class);
    }

    public function trainingSeminar()
    {
        return $this->belongsTo(TrainingSeminar::class);
    }

    public function trainingSeminarAttendance()
    {
        return $this->hasMany(
            TrainingSeminarAttendance::class,
            'client_employee_id',
            'client_employee_id'
        );
    }

    public function clientEmployeeTrainingSeminarAttendance()
    {
        return $this->hasMany(
            TrainingSeminarAttendance::class,
            'client_employee_id',
            'client_employee_id'
        )->where('training_seminar_id', $this->training_seminar_id);
    }

    public function trainingSeminarSchedule()
    {
        return $this->hasMany(TrainingSeminarSchedule::class, 'training_seminar_id', 'training_seminar_id');
    }
}
