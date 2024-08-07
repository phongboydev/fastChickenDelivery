<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class TrainingSeminarSchedule extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected $table = 'training_seminar_schedule';

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
        'training_seminar_id',
        'start_time',
        'end_time',
        'duration'
    ];

    public function attendance()
    {
        return $this->hasOne(TrainingSeminarAttendance::class);
    }

    public function clientEmployeeTrainingSeminarAttendance()
    {
        return $this->hasOneThrough(
            ClientEmployeeTrainingSeminar::class,
            TrainingSeminarAttendance::class,
            'training_seminar_schedule_id',
            'client_employee_id',
            'id',
            'id'
        );
    }
}
