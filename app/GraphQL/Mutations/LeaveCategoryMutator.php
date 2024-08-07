<?php

namespace App\GraphQL\Mutations;

use App\Models\LeaveCategory;
use App\Support\Constant;
use App\Exceptions\HumanErrorException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;


class LeaveCategoryMutator
{

    /**
     * @throws HumanErrorException|AuthenticationException
     */
    public function deleteLeaveCategory($root, array $args)
    {
        $authUser = Auth::user();
        $ids = $args['ids'];
        LeaveCategory::whereIn('id', $ids)->chunkById(10, function ($items) use ($authUser) {
            foreach ($items as $item) {
                // Check permission
                $isPermission = $this->isPermission($authUser, $item->client_id);
                if (!$isPermission) {
                    throw new AuthenticationException(__("error.permission"));
                }
                $item->clientEmployeeLeaveManagementByMonth()->delete();
                $item->clientEmployeeLeaveManagement()->delete();
                $item->delete();
            }
        });
        return LeaveCategory::where('client_id', $authUser)->get();
    }

    public function isPermission($user, $clientId)
    {
        $isHavePermission = false;
        if (!$user->isInternalUser()) {
            if ($user->client_id == $clientId && $user->hasDirectPermission('manage-workschedule') ||
                $user->client_id == $clientId && $user->getRole() === Constant::ROLE_CLIENT_MANAGER) {
                $isHavePermission = true;
            }
        }
        return $isHavePermission;
    }

}
