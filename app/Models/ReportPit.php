<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\User;
use App\Models\ClientEmployee;
use App\Models\IglocalEmployee;
use App\Models\IglocalAssignment;
use App\Models\CalculationSheet;
use App\Models\Concerns\HasAssignment;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ReportPit extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait, HasAssignment;
    use Concerns\UsesUuid;

    protected $table = 'report_pits';

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
        'name',
        'client_id',
        'date_from_to',
        'form_data',
        'original_creator_id',
        'duration_type',
        'quy_value',
        'quy_year',
        'thang_value',
        'status',
        'export_status',
        'loai_to_khai',
        'code',
        'created_at',
        'updated_at',
        'approved_comment',
        'trang_thai_xu_ly'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('ReportPIT');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        $user = Auth::user();

        if ($user->isInternalUser()) {

            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        } else {
            $normalPermissions = ["manage-payroll"];
            $advancedPermissions = ["advanced-manage-payroll"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-list-read"]);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query->where('client_id', '=', $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
