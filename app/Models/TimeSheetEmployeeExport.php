<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSheetEmployeeExport extends Model
{
    protected $fillable = [
        'name',
        'path',
        'status',
        'user_id',
        'from_date',
        'to_date'
    ];
}
