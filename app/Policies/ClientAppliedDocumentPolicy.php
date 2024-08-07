<?php

namespace App\Policies;

use App\Models\CcClientEmail;
use App\User;
use App\Models\ClientAppliedDocument;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class ClientAppliedDocumentPolicy
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
     * @param ClientAppliedDocument $clientAppliedDocument
     *
     * @return mixed
     */
    public function view(User $user, ClientAppliedDocument $clientAppliedDocument)
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
        //Only send mail to users which are belong to this client
        if (isset($injected['to_client_user_ids'])) {
            $countUsers = User::where('client_id', $injected['client_id'])
                ->whereIn('id', $injected['to_client_user_ids'])
                ->count();
            if ($countUsers < count($injected['to_client_user_ids'])) {
                return false;
            }
        }

        //Only cc mails which are belong to this client
        if (isset($injected['cc_client_email_ids'])) {
            $countUsers = CcClientEmail::where('client_id', $injected['client_id'])
                ->whereIn('id', $injected['cc_client_email_ids'])
                ->count();
            if ($countUsers < count($injected['cc_client_email_ids'])) {
                return false;
            }
        }

        if (!$user->isInternalUser()) {

            if ($user->client_id != $injected['client_id']) {
                return false;
            }

            return $user->checkHavePermission(['permission_apply_document'], []);
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
     * @param ClientAppliedDocument $clientAppliedDocument
     *
     * @return mixed
     */
    public function update(User $user, ClientAppliedDocument $clientAppliedDocument)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id != $clientAppliedDocument->client_id) {
                return false;
            }

            return $user->checkHavePermission(['permission_apply_document'], []);
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($clientAppliedDocument->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($clientAppliedDocument->client_id)) {
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
     * @param ClientAppliedDocument  $clientAppliedDocument
     *
     * @return mixed
     */
    public function delete(User $user, ClientAppliedDocument $clientAppliedDocument)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id != $clientAppliedDocument->client_id) {
                return false;
            }

            return $user->checkHavePermission(['permission_apply_document'], []);
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($clientAppliedDocument->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($clientAppliedDocument->client_id)) {
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
     * @param ClientAppliedDocument $clientAppliedDocument
     *
     * @return mixed
     */
    public function restore(User $user, ClientAppliedDocument $clientAppliedDocument)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the client employee overtime request.
     *
     * @param User                          $user
     * @param ClientAppliedDocument $clientAppliedDocument
     *
     * @return mixed
     */
    public function forceDelete(User $user, ClientAppliedDocument $clientAppliedDocument)
    {
        //
    }

    public function upload(User $user, ClientAppliedDocument $clientAppliedDocument)
    {
        if (!$user->isInternalUser()) {

            if ($user->client_id == $clientAppliedDocument->client_id) {
                return true;
            }

            return false;
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            }else{
                if (!empty($clientAppliedDocument->client_id)) {
                    if ($user->iGlocalEmployee->isAssignedFor($clientAppliedDocument->client_id)) {
                        return true;
                    }
                }
                return false;
            }
        }
    }
}
