<?php

namespace App\Models;

use App\Models\ClientWorkflowSetting;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Models\ClientEmployee;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * Class PayrollAccountantTemplate
 * @package App\Models
 */
class PayrollAccountantTemplate extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'payroll_accountant_templates';

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
        'client_id',
        'loop_direction',
        'title',
        'group_type',
        'template_columns',
    ];

    protected $casts = [
        'template_columns' => 'array'
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
        /** @var User $user */
        $user = Auth::user();

        if(!$user->isInternalUser()) {

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission('manage-payroll'))
            {
                return $query->where("{$this->getTable()}.client_id", $user->client_id);
            }else{
                return $query->whereNull('id');
            }

        }else{
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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
