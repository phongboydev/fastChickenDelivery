<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Znck\Eloquent\Traits\BelongsToThrough;

class TrainingSeminarAttendance extends Model
{
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected $table = 'training_seminar_attendance';

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
        'client_employee_id',
        'training_seminar_schedule_id',
        'training_seminar_id',
        'note',
        'state'
    ];



    public function getClientIdAttribute()
    {
        return $this->trainingSeminar()->value('client_id');
    }

    public function clientEmployees()
    {
        return $this->hasMany(ClientEmployeeTrainingSeminar::class);
    }

    public function trainingSeminarSchedule()
    {
        return $this->belongsTo(TrainingSeminarSchedule::class);
    }

    public function trainingSeminar()
    {
        return $this->belongsToThrough(TrainingSeminar::class, TrainingSeminarSchedule::class);
    }
}
