<?php

namespace App\Models;

use App\Models\ClientWorkflowSetting;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Client;
use App\Models\CalculationSheetTemplate;
use App\Models\ClientEmployee;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $leader_id
 * @property string $staff_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $client_id
 * @property ClientEmployee $staff
 * @property ClientEmployee $leader
 */
class CalculationSheetTemplateAssignment extends Model
{
    use Concerns\UsesUuid, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'calculation_sheet_template_assignments';

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
    protected $fillable = ['client_employee_id', 'template_id', 'client_id', 'created_at', 'updated_at', 'sort_by'];

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployee::class, 'client_employee_id');
    }

    /**
     * @return BelongsTo
     */
    public function template()
    {
        return $this->belongsTo(CalculationSheetTemplate::class, 'template_id');
    }

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
        /** @var User */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser())
        {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission('manage-payroll'))
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
