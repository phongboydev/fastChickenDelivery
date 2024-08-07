<?php

namespace App\Models;

use App\Models\ClientWorkflowSetting;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_id
 * @property string $readable_name
 * @property string $variable_name
 * @property string $scope
 * @property float $variable_value
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 */
class ClientCustomVariable extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'client_custom_variables';

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
    protected $fillable = ['client_id', 'readable_name', 'variable_name', 'scope', 'variable_value', 'sort_order', 'is_visible', 'created_at', 'updated_at'];

    protected $casts = [
        'variable_value' => 'float',
        'sort_order' => 'int',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if(($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission('manage-payroll')) || $user->hasDirectPermission('manage-employee'))
            {
                return $query->where("{$this->getTable()}.client_id", $user->client_id);
            }else{
                return $query->whereNull('id');
            }

        } else {

            if($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
