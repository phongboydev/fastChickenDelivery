<?php

namespace App\Policies;

use App\User;
use App\Models\ContractTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
class ContractTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the client employee overtime request.
     *
     * @param User                          $user
     * @param ContractTemplate $contractTemplate
     *
     * @return mixed
     */
    public function view(User $user, ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Determine whether the user can create client employee overtime requests.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $injected['client_id']) {
                return true;
            }

            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($injected['client_id'])) {
                    if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the client employee overtime request.
     *
     * @param User                          $user
     * @param ContractTemplate $contractTemplate
     *
     * @return mixed
     */
    public function update(User $user, ContractTemplate $contractTemplate)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $contractTemplate->client_id) {
                return true;
            }

            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($contractTemplate->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($contractTemplate->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the client employee overtime request.
     *
     * @param  User  $user
     * @param ContractTemplate  $contractTemplate
     *
     * @return mixed
     */
    public function delete(User $user, ContractTemplate $contractTemplate)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $contractTemplate->client_id) {
                return true;
            }

            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($contractTemplate->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($contractTemplate->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the client employee overtime request.
     *
     * @param User                          $user
     * @param ContractTemplate $contractTemplate
     *
     * @return mixed
     */
    public function restore(User $user, ContractTemplate $contractTemplate)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param ContractTemplate $contractTemplate
     *
     * @return mixed
     */
    public function forceDelete(User $user, ContractTemplate $contractTemplate)
    {
        //
    }
    
    public function upload(User $user, ContractTemplate $contractTemplate)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $contractTemplate->client_id) {
                return true;
            }

            return false;
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($contractTemplate->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($contractTemplate->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }
}
