<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Models\Concerns\HasAssignment;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

class SocialSecurityProfile extends Model implements HasMedia
{
    use UsesUuid, InteractsWithMedia, MediaTrait, HasAssignment;

    protected $table = 'social_security_profiles';

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

    public function getMediaModel()
    {
        return $this->getMedia('SocialSecurityProfile');
    }

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'client_employee_id',
        'social_security_profile_request_id',
        'loai_nhap_ke_khai',
        'ho_va_ten',
        'ma_nhan_vien',
        'so_so_bhxh',
        'so_cmnd_hc',
        'so_dien_thoai',
        'noi_dk_kcb_ban_dau_tinh',
        'noi_dk_kcb_ban_dau_benh_vien',
        'noi_dk_kcb_ban_dau_benh_vien_code',
        'can_cu_theo',
        'so_van_ban',
        'ngay_hieu_luc',
        'ngay_ket_thuc',
        'muc_luong_moi',
        'chuc_vu_moi',
        'gioi_tinh',
        'ngay_sinh',
        'quoc_tich',
        'dan_toc',
        'sdt_lien_he',
        'email',
        'so_ho_gia_dinh_da_cap',
        'muc_tien_dong',
        'phuong_thuc_dong',
        'noi_dung_thay_doi_yeu_cau',
        'department',
        'trang_thai',
        'ndk_giay_khai_sinh_tinh',
        'ndk_giay_khai_sinh_huyen',
        'ndk_giay_khai_sinh_xa',
        'noi_dk_giay_khai_sinh',
        'noi_dk_giay_khai_sinh_full_address',
        'dia_chi_lien_he_nhan_ho_so_full_address',
        'dia_chi_lien_he_nhan_ho_so',
        'dc_nhan_ho_so_tinh',
        'dc_nhan_ho_so_huyen',
        'dc_nhan_ho_so_xa',
        'comment',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function socialSecurityProfileRequest()
    {
        return $this->belongsTo('App\Models\SocialSecurityProfileRequest');
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
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-social-insurance-read"]);
            }
            $query->where("{$this->getTable()}.client_id", $user->client_id);
            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query;
            } else {
                return $query->whereNull("{$this->getTable()}.client_employee_id", $user->clientEmployee->id);
            }
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
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
