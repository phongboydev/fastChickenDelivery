<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientTeacherTrainingSeminar extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected $table = 'client_teacher_training_seminar';

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
        'teacher_training_id',
        'training_seminar_id',
        'created_at',
        'updated_at'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'code', 'name'
    ];

    public function getCodeAttribute()
    {   
        return ClientTeacherTraining::find($this->teacher_training_id)->code;
    }

    public function getNameAttribute()
    {
        return ClientTeacherTraining::find($this->teacher_training_id)->name;
    }
}
