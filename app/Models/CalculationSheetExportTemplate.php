<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\ClientWorkflowSetting;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $calculation_sheet_id
 * @property string $client_employee_id
 * @property float $calculated_value
 * @property string $is_disabled
 * @property CalculationSheet $calculationSheet
 * @property ClientEmployee $clientEmployee
 */

class CalculationSheetExportTemplate extends Model implements HasMedia
{
    use InteractsWithMedia, UsesUuid, MediaTrait, HasAssignment, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'calculation_sheet_export_templates';

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

    public function getMediaModel() { return $this->getMedia('CalculationSheetExportTemplate'); }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @var array
     */
    protected $fillable = ['name', 'file_name', 'client_id'];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {

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
