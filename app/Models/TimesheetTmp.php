<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetTmp extends Model
{
    use HasFactory;

    protected $table = 'timesheet_tmp';

    protected $fillable = [
        'import_key',
        'client_employee_id',
        'check_in',
        'check_out',
    ];

}
