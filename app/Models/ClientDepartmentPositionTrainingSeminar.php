<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ClientDepartmentPositionTrainingSeminar extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'client_department_position_training_seminar';

    protected $guarded = [];

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
    protected $fillable = ['training_seminar_id', 'position'];

}
