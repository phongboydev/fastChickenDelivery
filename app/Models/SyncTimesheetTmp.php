<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncTimesheetTmp extends Model
{
    use HasFactory;
    public $timestamps = true;
    protected $table = 'sync_timesheet_tmp';
    protected $fillable = [
        'client_id',
        'client_employee_id',
        'code',            
        'date_time',
        'data',
        'message_error',
        'status'
    ];
    
    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'client_employee_id');
    }
}

