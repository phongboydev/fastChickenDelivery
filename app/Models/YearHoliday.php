<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $log_date
 * @property string $activity
 * @property string $work_place
 * @property int $working_hours
 * @property int $rest_hours
 * @property int $overtime_hours
 * @property string $check_in
 * @property string $check_out
 * @property string $leave_type
 * @property string $attentdant_status
 * @property string $work_status
 * @property string $note
 * @property string $created_at
 * @property string $updated_at
 */
class YearHoliday extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'year_holidays';

    public $timestamps = false;

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
    protected $fillable = ['year', 'day'];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        return $user->isInternalUser();
    }
}
