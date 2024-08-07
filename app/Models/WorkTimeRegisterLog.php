<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTimeRegisterLog extends Model
{
    use UsesUuid, SoftDeletes, HasFactory;

    protected static $logAttributes = ['*'];

    protected $table = 'work_time_register_logs';

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'cal_sheet_client_employee_id',
        'work_time_register_timesheet_id',
        'deleted_at'];

    public function calculationSheetClientEmployee()
    {
        return $this->belongsTo(CalculationSheetClientEmployee::class, 'cal_sheet_client_employee_id');
    }

    public function workTimeRegisterTimesheet()
    {
        return $this->belongsTo(WorkTimeRegisterTimesheet::class, 'work_time_register_timesheet_id');
    }
}
