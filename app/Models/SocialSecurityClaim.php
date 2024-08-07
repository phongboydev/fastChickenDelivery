<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SocialSecurityClaim extends Model implements HasMedia
{
    use Concerns\UsesUuid, HasAssignment, InteractsWithMedia, MediaTrait;

    protected $table = 'social_security_claims';

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
        'client_employee_id',
        'state',
        'client_approved',
        'social_insurance_number',
        'claimed_amount',
        'reason',
        'cd_claim_bao_hiem',
        'cd_claim_bao_hiem_sub',
        'cd_claim_bao_hiem_sub_sub',
        'cd_claim_bh_tu_ngay',
        'cd_claim_bh_den_ngay',
        'cd_claim_bh_tong_so_ngay_nghi',

        'cd_om_dau_ten_benh',
        'cd_om_dau_tuyen_benh_vien',
        'cd_om_dau_benh_dai_ngay',

        'cd_thai_san_ngay_sinh_con',
        'cd_thai_san_phau_thuat_thai_duoi_32t',
        'cd_thai_san_nghi_duong_thai',
        'cd_thai_san_ngay_nhan_con_nuoi',
        'cd_thai_san_tuoi_thai',
        'cd_thai_san_bien_phap_tranh_thai',
        'cd_thai_san_dieu_kien_sinh_con',
        'cd_thai_san_dieu_kien_khi_kham_thai',
        'cd_thai_san_cha_nghi_cham_con',
        'cd_thai_san_ngay_di_lam_thuc_te',
        'cd_thai_san_ngay_con_chet',
        'cd_thai_san_so_con_chet_khi_sinh',
        'cd_thai_san_ngay_me_chet',
        'cd_thai_san_ngay_ket_luan',

        'ds_ph_suc_khoe_ngay_tro_lai_lam_viec',
        'ds_ph_suc_khoe_ngay_dam_dinh',
        'tinh_trang_chung_tu_lien_quan',
        'ttgqhs_ngay_nop_ho_so',
        'ttgqhs_ngay_hen_tra_ket_qua',
        'ttgqhs_so_ho_so_bhxh_da_ke_khai',
        'ttgqhs_tong_so_ngay_duoc_tinh_huong_tro_cap',
        'ttgqhs_so_tien_duoc_huong',

        'ngay_ke_khai_va_luu_tam_ho_so',
        'ngay_tra_ket_qua',
        'bo_phan_cd_bhxh_date',
        'bo_phan_cd_bhxh_reviewer',
        'bo_phan_khtc_date',
        'bo_phan_khtc_reviewer',
        'bo_phan_tn_tkq_date',
        'bo_phan_tn_tkq_reviewer',

        'hinh_thuc_nhan',
        'bhxh_bank_name',
        'bhxh_bank_account',
        'bhxh_bank_account_number',
        'bhxh_bank_code',
        'bhxh_bank_province',

        'rejected_comment',
        'note'
    ];

    public function getMediaModel()
    {
        return $this->getMedia('SocialSecurityClaim');
    }

    public function getFileBhxhTraVeAttribute()
    {
        $mediaItems = $this->getMedia('ket_qua_bhxh_tra_ve');

        $attachments = [];

        if (count($mediaItems) > 0) {
            foreach ($mediaItems as $key => $item) {
                $attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'url' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'description' => $item->hasCustomProperty('description') ? $item->getCustomProperty('description') : ''
                ];
            }
        }

        return $attachments;
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    /**
     * Get all of the post's comments.
     */
    public function approves()
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
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
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            $normalPermissions = ["manage-social"];
            $advancedPermissions = ["advanced-manage-payroll"];

            if (!empty($params['advanced_permissions']) && is_array($params['advanced_permissions'])) {
                $advancedPermissions = array_merge($advancedPermissions, $params['advanced_permissions']);
            } else {
                $advancedPermissions = array_merge($advancedPermissions, ["advanced-manage-payroll-social-insurance-read"]);
            }

            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                return $query->where('client_id', '=', $user->client_id);
            } else {
                return $query->where('client_employee_id', '=', $user->clientEmployee->id);
            }
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
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
