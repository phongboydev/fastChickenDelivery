<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasAssignment;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\SocialSecurityProfile;

/**
 * @property string $id
 * @property string $client_id
 * @property string $batch_no
 * @property string $calculation_sheet_id
 * @property string $status
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 */

class SocialSecurityProfileRequest extends Model implements HasMedia
{
    use UsesUuid, InteractsWithMedia, MediaTrait, SoftDeletes, HasAssignment;

    protected $table = 'social_security_profile_requests';

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
        'ten_ho_so',
        'ma_ho_so',
        'ma_don_vi',
        'loai_ho_so_type',
        'loai_ho_so_sub',
        'loai_ho_so_from_date',
        'loai_ho_so_to_date',
        'tinh_trang_giai_quyet_ho_so',
        'ghi_chu_ho_so_ke_khai_loi',
        'ngay_ke_khai_va_luu_tam_ho_so',
        'ngay_nop_ho_so',
        'so_ho_so_bhxh_da_ke_khai',
        'ngay_hen_tra_ket_qua',
        'tinh_trang_chung_tu_lien_quan',
        'tinh_trang_phia_khach_hang',

        'loai_nhap_ke_khai',
        'bo_phan_cd_bhxh_date',
        'bo_phan_cd_bhxh_reviewer',
        'bo_phan_khtc_date',
        'bo_phan_khtc_reviewer',
        'bo_phan_tn_tkq_date',
        'bo_phan_tn_tkq_reviewer',

        'note',
        'approved_date',
        'approved_by',
        'approved_comment',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('SocialSecurityProfileRequest');
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo('App\User', 'creator_id');
    }

    public function socialSecurityProfiles()
    {
        return $this->hasMany(SocialSecurityProfile::class, 'social_security_profile_request_id');
    }

    public function scopeAuthUserAccessible($query, $params = null)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $normalPermissions = ["manage-social"];
            $advancedPermissions = ["advanced-manage-payroll"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-social-declaration-read"]);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query->where('client_id', $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
