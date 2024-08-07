<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientSettingConditionCompare extends Model
{
    use UsesUuid, LogsActivity, HasAssignment, SoftDeletes;

    protected static $logAttributes = ['*'];

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
    protected $fillable = ['key_condition', 'comparison_operator', 'value','client_id','name_variable', 'sub_level'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @property Builder $query
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            $normalPermissions = ['manage-payroll'];
            $advancedPermissions = ['advanced-manage-payroll-list-read'];

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {
             return $query;
        }
    }
}
