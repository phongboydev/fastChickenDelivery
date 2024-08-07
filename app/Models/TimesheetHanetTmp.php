<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetHanetTmp extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $table = 'timesheet_hanet_tmp';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    /**
     * @var array
     */
    protected $fillable = [
        'client_employee_id',
        'client_id',
        'date_time',
        'alias_id',
        'person_id',
        'data_hanet',
        'message_error',
        'status'
    ];
}
