<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Support\Facades\Auth;
use Znck\Eloquent\Traits\BelongsToThrough;

class ClientEmployeePayslipComplaint extends Model
{
    use UsesUuid, HasFactory, LogsActivity, SoftDeletes, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_payslip_complaints';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = ['calculation_sheet_client_employee_id', 'description', 'responder_id', 'feedback', 'state'];

    public function scopeAuthUserAccessible($query, $params = null)
    {
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $normalPermissions = ["manage-social"];
            $advancedPermissions = ["advanced-manage-payroll"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-social-insurance-read"]);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                $clientId = $user->client_id;
                return $query->whereRelation('calculationSheetClientEmployee', function ($query) use ($clientId) {
                    $query->whereRelation('client', function ($q) use ($clientId) {
                        $q->where('clients.id', $clientId);
                    });
                });
            } else {
                $clientEmployeeId = $user->clientEmployee->id;
                return $query->whereRelation('clientEmployee', function ($query) use ($clientEmployeeId) {
                    $query->where('calculation_sheet_client_employees.client_employee_id', $clientEmployeeId);
                });
            }
        } else {
            //We cannot use belongToClientAssignedTo function here
            //Because this table need has relationship with client to use this.
            return $query;
        }
    }

    public function scopeManagerAccessible($query)
    {
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                default:
                    if ($user->hasAnyPermission(['manage-payroll-complaint'])) {
                        return $query->whereRelation('calculationSheetClientEmployee', function ($query) use ($user) {
                            $query->whereRelation('client', function ($q) use ($user) {
                                $q->where('clients.id', $user->client_id);
                            });
                        });
                    } else {
                        return $query->whereRelation('clientEmployee', function ($query) use ($user) {
                            $query->where('calculation_sheet_client_employees.client_employee_id', $user->clientEmployee->id);
                        });
                    }
            }
        } else {
            //We cannot use belongToClientAssignedTo function here
            //Because this table need has relationship with client to use this.
            return $query;
        }
    }

    /**
     * @return BelongsTo
     */
    public function calculationSheetClientEmployee()
    {
        return $this->belongsTo(CalculationSheetClientEmployee::class);
    }

    public function calculationSheet()
    {
        return $this->belongsToThrough(CalculationSheet::class, CalculationSheetClientEmployee::class);
    }

    public function clientEmployee()
    {
        return $this->belongsToThrough(clientEmployee::class, CalculationSheetClientEmployee::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class);
    }
}
