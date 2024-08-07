<?php

namespace App\GraphQL\Mutations;


use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\Approve;
use App\Models\ApproveGroup;
use App\Models\SocialSecurityProfile;
use App\Models\SocialSecurityProfileHistory;
use App\Models\SocialSecurityProfileRequest;
use App\Models\ApproveFlowUser;
use Illuminate\Support\Facades\Auth;

class SocialSecurityProfileRequestMutator
{

    public function create($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $clientEmployees = $args['employees'];
        $defaultClientEmployeeGroup = '0';

        $socialSecurityProfileRequestContent = [
            'client_id' => $args['client_id'],
            'status' => 'pending',
            'ma_ho_so' => isset($args['ma_ho_so']) ? $args['ma_ho_so'] : NULL,
            'ma_don_vi' => isset($args['ma_don_vi']) ? $args['ma_don_vi'] : NULL,
            'ten_ho_so' => $args['ten_ho_so'],
            'loai_ho_so_type' => $args['loai_ho_so_type'],
            'loai_ho_so_sub' => $args['loai_ho_so_sub'],
            'loai_ho_so_from_date' => isset($args['loai_ho_so_from_date']) ? $args['loai_ho_so_from_date'] : NULL,
            'loai_ho_so_to_date' => isset($args['loai_ho_so_to_date']) ? $args['loai_ho_so_to_date'] : NULL,
            'tinh_trang_giai_quyet_ho_so' => isset($args['tinh_trang_giai_quyet_ho_so']) ? $args['tinh_trang_giai_quyet_ho_so'] : '',
            'ghi_chu_ho_so_ke_khai_loi' => isset($args['ghi_chu_ho_so_ke_khai_loi']) ? $args['ghi_chu_ho_so_ke_khai_loi'] : '',
            'ngay_ke_khai_va_luu_tam_ho_so' => isset($args['ngay_ke_khai_va_luu_tam_ho_so']) ? $args['ngay_ke_khai_va_luu_tam_ho_so'] : NULL,
            'ngay_nop_ho_so' => isset($args['ngay_nop_ho_so']) ? $args['ngay_nop_ho_so'] : NULL,
            'so_ho_so_bhxh_da_ke_khai' => isset($args['so_ho_so_bhxh_da_ke_khai']) ? $args['so_ho_so_bhxh_da_ke_khai'] : '',
            'ngay_hen_tra_ket_qua' => isset($args['ngay_hen_tra_ket_qua']) ? $args['ngay_hen_tra_ket_qua'] : NULL,
            'tinh_trang_chung_tu_lien_quan' => isset($args['tinh_trang_chung_tu_lien_quan']) ? $args['tinh_trang_chung_tu_lien_quan'] : '',
            'loai_nhap_ke_khai' => isset($args['loai_nhap_ke_khai']) ? $args['loai_nhap_ke_khai'] : '',
            'bo_phan_cd_bhxh_date' => isset($args['bo_phan_cd_bhxh_date']) ? $args['bo_phan_cd_bhxh_date'] : NULL,
            'bo_phan_cd_bhxh_reviewer' => isset($args['bo_phan_cd_bhxh_reviewer']) ? $args['bo_phan_cd_bhxh_reviewer'] : '',
            'bo_phan_khtc_date' => isset($args['bo_phan_khtc_date']) ? $args['bo_phan_khtc_date'] : NULL,
            'bo_phan_khtc_reviewer' => isset($args['bo_phan_khtc_reviewer']) ? $args['bo_phan_khtc_reviewer'] : '',
            'bo_phan_tn_tkq_date' => isset($args['bo_phan_tn_tkq_date']) ? $args['bo_phan_tn_tkq_date'] : NULL,
            'bo_phan_tn_tkq_reviewer' => isset($args['bo_phan_tn_tkq_reviewer']) ? $args['bo_phan_tn_tkq_reviewer'] : '',
        ];

        $socialSecurityProfileRequest = SocialSecurityProfileRequest::create($socialSecurityProfileRequestContent);

        foreach ($clientEmployees as $employee) {

            $employee['social_security_profile_request_id'] = $socialSecurityProfileRequest->id;

            $socialSecurityProfile = SocialSecurityProfile::create($employee);

            if (isset($employee['attach_files']) && $employee['attach_files']) {

                foreach($employee['attach_files'] as $attachFile) {
                    $mediaItem = $socialSecurityProfile->addMediaFromDisk($attachFile['path'], 'minio')
                    ->preservingOriginal()
                    ->storingConversionsOnDisk('minio')
                    ->toMediaCollection('SocialSecurityProfile', 'minio');
    
                    if(isset($attachFile['description'])){
                        $mediaItem->setCustomProperty('description', $attachFile['description']);
                        $mediaItem->save();
                    }
                }
            }
        }

        if (isset($args['reviewer_id']) && $args['reviewer_id']) {

            $flowName = 'CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE';
            $reviewerId = $args['reviewer_id'];

            $approveGroup = ApproveGroup::create([
                'client_id' => $args['client_id'],
                'type' => 'CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE',
            ]);

            $approveFlowUser = ApproveFlowUser::where('user_id', $reviewerId)
                ->with('approveFlow')
                ->whereHas('approveFlow', function ($query) use ($flowName, $defaultClientEmployeeGroup) {
                    return $query->where('flow_name', $flowName)->where('group_id', $defaultClientEmployeeGroup);
                })->get();

            if ($approveFlowUser->isNotEmpty()) {

                $sortedApproveFlow = $approveFlowUser->sortBy(function ($item, $key) {
                        
                    return $item->toArray()['approve_flow']['step'];
                });
                $approveFlow = $sortedApproveFlow->values()->last()->toArray();
                $step = $approveFlow['approve_flow']['step'];
                
                $targetId = $socialSecurityProfileRequest->id;

                $approveNext = new Approve();
                $approveNext->fill([
                    'client_id' => $args['client_id'],
                    'type' => $flowName,
                    'content' => json_encode($socialSecurityProfileRequestContent),
                    'step' => $step,
                    'target_type' => 'App\Models\SocialSecurityProfileRequest',
                    'target_id' => $targetId,
                    'approve_group_id' => $approveGroup->id,
                    'creator_id' => Auth::user()->id,
                    'original_creator_id' => Auth::user()->id,
                    'assignee_id' => $reviewerId,
                    'is_final_step' => 0,
                    'client_employee_group_id' => $defaultClientEmployeeGroup
                ])->save();
            }
        }

        return $socialSecurityProfileRequest;
    }

    public function update($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $id = $args['id'];

        $clientEmployees = $args['employees'];

        $socialSecurityProfileRequestContent = [
            'client_id' => $args['client_id'],
            'ten_ho_so' => $args['ten_ho_so'],
            'ma_ho_so' => isset($args['ma_ho_so']) ? $args['ma_ho_so'] : NULL,
            'ma_don_vi' => $args['ma_don_vi'],
            'loai_ho_so_type' => $args['loai_ho_so_type'],
            'loai_ho_so_sub' => $args['loai_ho_so_sub'],
            'loai_ho_so_from_date' => isset($args['loai_ho_so_from_date']) ? $args['loai_ho_so_from_date'] : NULL,
            'loai_ho_so_to_date' => isset($args['loai_ho_so_to_date']) ? $args['loai_ho_so_to_date'] : NULL,
            'tinh_trang_giai_quyet_ho_so' => isset($args['tinh_trang_giai_quyet_ho_so']) ? $args['tinh_trang_giai_quyet_ho_so'] : '',
            'ghi_chu_ho_so_ke_khai_loi' => isset($args['ghi_chu_ho_so_ke_khai_loi']) ? $args['ghi_chu_ho_so_ke_khai_loi'] : '',
            'ngay_ke_khai_va_luu_tam_ho_so' => isset($args['ngay_ke_khai_va_luu_tam_ho_so']) ? $args['ngay_ke_khai_va_luu_tam_ho_so'] : NULL,
            'ngay_nop_ho_so' => isset($args['ngay_nop_ho_so']) ? $args['ngay_nop_ho_so'] : NULL,
            'so_ho_so_bhxh_da_ke_khai' => isset($args['so_ho_so_bhxh_da_ke_khai']) ? $args['so_ho_so_bhxh_da_ke_khai'] : '',
            'ngay_hen_tra_ket_qua' => isset($args['ngay_hen_tra_ket_qua']) ? $args['ngay_hen_tra_ket_qua'] : NULL,
            'tinh_trang_chung_tu_lien_quan' => isset($args['tinh_trang_chung_tu_lien_quan']) ? $args['tinh_trang_chung_tu_lien_quan'] : '',
            'loai_nhap_ke_khai' => isset($args['loai_nhap_ke_khai']) ? $args['loai_nhap_ke_khai'] : '',
            'bo_phan_cd_bhxh_date' => isset($args['bo_phan_cd_bhxh_date']) ? $args['bo_phan_cd_bhxh_date'] : NULL,
            'bo_phan_cd_bhxh_reviewer' => isset($args['bo_phan_cd_bhxh_reviewer']) ? $args['bo_phan_cd_bhxh_reviewer'] : '',
            'bo_phan_khtc_date' => isset($args['bo_phan_khtc_date']) ? $args['bo_phan_khtc_date'] : NULL,
            'bo_phan_khtc_reviewer' => isset($args['bo_phan_khtc_reviewer']) ? $args['bo_phan_khtc_reviewer'] : '',
            'bo_phan_tn_tkq_date' => isset($args['bo_phan_tn_tkq_date']) ? $args['bo_phan_tn_tkq_date'] : NULL,
            'bo_phan_tn_tkq_reviewer' => isset($args['bo_phan_tn_tkq_reviewer']) ? $args['bo_phan_tn_tkq_reviewer'] : '',
            'note' => isset($args['note']) ? $args['note'] : NULL,
        ];

        $socialSecurityProfileRequest = SocialSecurityProfileRequest::find($id);

        $socialSecurityProfileRequest->fill($socialSecurityProfileRequestContent);

        $socialSecurityProfileRequest->save();

        if($clientEmployees) {
            SocialSecurityProfile::where('social_security_profile_request_id', $id)->whereNotIn('client_employee_id', collect($clientEmployees)->pluck('client_employee_id')->all())->delete();

            foreach ($clientEmployees as $employee) {

                $employee['social_security_profile_request_id'] = $socialSecurityProfileRequest->id;

                $socialSecurityProfile = SocialSecurityProfile::updateOrCreate([
                    'social_security_profile_request_id' => $socialSecurityProfileRequest->id,
                    'client_employee_id' => $employee['client_employee_id']
                ], $employee);

                if (isset($employee['attach_files']) && $employee['attach_files']) {

                    foreach($employee['attach_files'] as $attachFile) {
                        $mediaItem = $socialSecurityProfile->addMediaFromDisk($attachFile['path'], 'minio')
                        ->preservingOriginal()
                        ->storingConversionsOnDisk('minio')
                        ->toMediaCollection('SocialSecurityProfile', 'minio');

                        if(isset($attachFile['description'])){
                            $mediaItem->setCustomProperty('description', $attachFile['description']);
                            $mediaItem->save();
                        }
                    }
                }
            }
        }

        if (isset($args['attach_file'])) {

            $socialSecurityProfileRequest->clearMediaCollection('SocialSecurityProfileRequest');

            $socialSecurityProfileRequest->addMediaFromDisk('temp/' . $args['attach_file'], 'minio')
                ->preservingOriginal()
                ->storingConversionsOnDisk('minio')
                ->toMediaCollection('SocialSecurityProfileRequest', 'minio');
        }

        return $socialSecurityProfileRequest;
    }

    public function history($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        return SocialSecurityProfileHistory::where('client_id', $user->client_id)
            ->where('client_employee_id', $user->clientEmployee->id)
            ->orderBy('created_at', 'ASC')
            ->get();
    }
}
