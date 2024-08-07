<?php

namespace App\Policies;

use App\Models\Allowance;
use App\User;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
class AllowancePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, Contract $contract)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
            return true;
        }else{
            return $user->iGlocalEmployee->isAssignedFor($injected['client_id']);
        }
    }


    public function update(User $user, Allowance $allowance)
    {
        if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
            return true;
        }else{
            return $user->iGlocalEmployee->isAssignedFor($allowance->client_id);
        }
    }

    public function delete(User $user, Allowance $allowance)
    {
        if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
            return true;
        }else{
            return $user->iGlocalEmployee->isAssignedFor($allowance->client_id);
        }
    }

}
