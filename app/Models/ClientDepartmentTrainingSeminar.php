<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class ClientDepartmentTrainingSeminar extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'client_department_training_seminar';

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
    protected $fillable = ['training_seminar_id', 'client_department_id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'client_department_name'
    ];

    public function getClientDepartmentNameAttribute()
    {
        return ClientDepartment::where('id', $this->client_department_id)->value('department');
    }

}
