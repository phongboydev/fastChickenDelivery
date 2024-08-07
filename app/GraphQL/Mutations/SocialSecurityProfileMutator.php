<?php

namespace App\GraphQL\Mutations;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Support\TemporaryMediaTrait;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use App\Models\Approve;
use App\Models\ApproveGroup;
use App\Models\SocialSecurityProfile;
use App\Models\SocialSecurityProfileHistory;
use App\Models\ClientEmployee;
use App\Models\SocialSecurityProfileRequest;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use App\Exceptions\CustomException;

class SocialSecurityProfileMutator
{

    public function getPaginate($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $order      = isset($args['order']) ? $args['order'] : 'ASC';
        $filterCode = isset($args['filterCode']) ? trim($args['filterCode']) : false;
        $filterStatus = isset($args['filterStatus']) ? trim($args['filterStatus']) : false;
        $clientID   = trim($args['clientID']);

        $perpage = isset($args['perPage']) ? $args['perPage'] : 10;
        $page = isset($args['page']) ? $args['page'] : '1';

        $items = SocialSecurityProfile::select('social_security_profiles.*')
            ->join('client_employees', 'social_security_profiles.client_employee_id', '=', 'client_employees.id')
            ->whereHas('clientEmployee', function (Builder $query) use ($filterCode) {

                if ($filterCode) {
                    $query->where('client_employees.code', '=', $filterCode);
                }

                return $query;
            })
            ->where('social_security_profiles.client_id', '=', $clientID);

        if ($filterStatus) {
            $items = $items->where('social_security_profiles.status', '=', $filterStatus);
        }

        $items = $items->orderBy('social_security_profiles.created_at', $order)
            ->authUserAccessible()
            ->paginate($perpage, ['social_security_profiles.*'], 'page', $page);

        return [
            'data'       => $items,
            'pagination' => [
                'total'        => $items->total(),
                'count'        => $items->count(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'total_pages'  => $items->lastPage()
            ],
        ];
    }

    public function create($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $clientEmployee = ClientEmployee::where('id', '=', $args['client_employee_id'])->first();

        $data = [
            'client_id' => $args['client_id'],
            'client_employee_id' => $args['client_employee_id'],
            'comment' => $args['comment'],
            'status' => 'requested'
        ];

        if (isset($args['social_insurance_number_no'])) {
            $data['social_insurance_number_no'] = trim($args['social_insurance_number_no']);
        }

        if (isset($args['salary_for_social_insurance_payment'])) {
            $data['salary_for_social_insurance_payment'] = trim($args['salary_for_social_insurance_payment']);
        }

        if ($clientEmployee->effective_date_of_social_insurance) {
            $data['effective_date_of_social_insurance'] = $clientEmployee->effective_date_of_social_insurance;
        }

        $socialSecurityProfile = SocialSecurityProfile::create($data);

        return $socialSecurityProfile;
    }

    public function createMultiple($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {

        $clientEmployees = ClientEmployee::whereIn('id', $args['client_employee_ids'])->get();

        if ($clientEmployees->isNotEmpty()) {

            $socialSecurityProfileRequestContent = [];

            foreach ($clientEmployees as $employee) {

                $data = [
                    'client_id' => $args['client_id'],
                    'client_employee_id' => $employee->id,
                    'comment' => $args['comment'],
                    'status' => 'moi',
                    'tinh_trang' => $args['tinh_trang'],
                    'tinh_trang_type' => $args['tinh_trang_type'],
                    'tinh_trang_type_sub' => $args['tinh_trang_type_sub'],
                    'tinh_trang_from_date' => $args['tinh_trang_from_date'],
                    'tinh_trang_to_date' => $args['tinh_trang_to_date'],
                    'noi_dang_ki_kcb_ban_dau' => $args['noi_dang_ki_kcb_ban_dau'],
                    'muc_luong_dieu_chinh' => $args['muc_luong_dieu_chinh'],
                    'chuc_vu_dieu_chinh' => $args['chuc_vu_dieu_chinh'],
                    'tinh_trang_chung_tu_lien_quan' => $args['tinh_trang_chung_tu_lien_quan'],
                    'salary_for_social_insurance_payment' => 0,
                    'tinh_trang_phia_khach_hang' => 'cho_phe_duyet'
                ];

                if ($employee->effective_date_of_social_insurance) {
                    $data['effective_date_of_social_insurance'] = $employee->effective_date_of_social_insurance;
                }

                $socialSecurityProfile = SocialSecurityProfile::create($data);

                if (isset($args['attach_file']) && $args['attach_file']) {

                    $socialSecurityProfile->addMediaFromDisk('temp/' . $args['attach_file'], 'minio')
                        ->preservingOriginal()
                        ->storingConversionsOnDisk('minio')
                        ->toMediaCollection('SocialSecurityProfile', 'minio');
                }

                $socialSecurityProfileRequestContent[] = array_merge($data, [
                    'profile_id' => $socialSecurityProfile->id,
                    'client_employee_name' => $employee->full_name,
                    'client_employee_code' => $employee->code,
                ]);
            }

            $socialSecurityProfileRequest = SocialSecurityProfileRequest::create([
                'client_id' => $args['client_id'],
                'status' => 'pending',
                'content' => json_encode($socialSecurityProfileRequestContent),
            ]);

            if (isset($args['attach_file']) && $args['attach_file']) {

                $socialSecurityProfileRequest->addMediaFromDisk('temp/' . $args['attach_file'], 'minio')
                    ->preservingOriginal()
                    ->storingConversionsOnDisk('minio')
                    ->toMediaCollection('SocialSecurityProfileRequest', 'minio');
            }

            $approveGroup = ApproveGroup::create([
                'client_id' => $args['client_id'],
                'type' => 'CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE',
            ]);

            $approveModel = new Approve();
            $approveModel->fill([
                'client_id' => $args['client_id'],
                'approve_group_id' => $approveGroup->id,
                'type' => 'CLIENT_REQUEST_SOCIAL_SECURITY_PROFILE',
                'creator_id' => Auth::user()->id,
                'content' => json_encode($socialSecurityProfileRequestContent),
                'assignee_id' => $args['reviewer_id'],
                'step' => 1,
                'target_type' => 'App\Models\SocialSecurityProfileRequest',
                'target_id' => $socialSecurityProfileRequest->id,
                'original_creator_id' => Auth::user()->id,
                'client_employee_group_id' => '0'
            ]);
            $approveModel->save();

            return true;
        }

        return false;
    }

    public function history($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return SocialSecurityProfileHistory::authUserAccessible()->where('profile_id', $args['profile_id'])
            ->orderBy('created_at', 'ASC')
            ->get();
    }
}
