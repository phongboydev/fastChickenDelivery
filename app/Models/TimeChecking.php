<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * TODO: table TimeChecking và Checking đang được build song song với mục đích khác nhau.
 * Cần tìm giải pháp chung sau này.
 */

class TimeChecking extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected $table = 'time_checking';

    public $timestamps = true;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        'datetime',
        'client_employee_id',
        'timesheet_id',
        'hanet_log_id',
        'source','info_app',
        'location_checkin',
        'ssid',
        'bssid',
        'longitude',
        'latitude',
        'user_location_input',
    ];

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployee::class);
    }

    /**
     * @return BelongsTo
     */
    public function timesheets()
    {
        return $this->belongsTo(Timesheet::class, 'timesheet_id', 'id');
    }
}
