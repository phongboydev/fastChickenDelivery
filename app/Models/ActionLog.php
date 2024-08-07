<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\Constant;

class ActionLog extends Model
{
    use UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'action_logs';

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
        'user_id',
        'client_employee_code',
        'client_code',
        'username',
        'app_name',
        'screen_name',
        'action_name',
        'created_at',
        'updated_at'
    ];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }
    
    public function scopeAuthUserAccessible($query)
    {
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            return $query->where('client_code', '=', $user->client->code);
        } else {
            if($role == Constant::ROLE_INTERNAL_DIRECTOR) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
