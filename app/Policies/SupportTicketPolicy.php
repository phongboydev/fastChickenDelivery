<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\User;
use App\Models\ClientEmployee;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SupportTicketPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any support tickets.
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
     * Determine whether the user can view the support ticket.
     *
     * @param User                $user
     * @param  \App\SupportTicket $supportTicket
     *
     * @return mixed
     */
    public function view(User $user, SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Determine whether the user can create support tickets.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    if ($user->id == $injected['user_id'] &&
                        $user->clientEmployee->client_id == $injected['client_id']) {
                        return true;
                    }
                    return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Determine whether the user can update the support ticket.
     *
     * @param  User  $user
     * @param  \App\SupportTicket  $supportTicket
     *
     * @return mixed
     */
    public function update(User $user, SupportTicket $supportTicket)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    if ($user->id == $supportTicket->user_id) {
                        return true;
                    }
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if ($user->iGlocalEmployee->isAssignedFor($supportTicket->client_id)) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return true;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the support ticket.
     *
     * @param  User  $user
     * @param  \App\SupportTicket  $supportTicket
     *
     * @return mixed
     */
    public function delete(User $user, SupportTicket $supportTicket)
    {
        return false;
    }

    /**
     * Determine whether the user can restore the support ticket.
     *
     * @param  User  $user
     * @param  \App\SupportTicket  $supportTicket
     *
     * @return mixed
     */
    public function restore(User $user, SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the support ticket.
     *
     * @param  User  $user
     * @param  \App\SupportTicket  $supportTicket
     *
     * @return mixed
     */
    public function forceDelete(User $user, SupportTicket $supportTicket)
    {
        //
    }

    public function upload(User $user, SupportTicket $supportTicket): bool
    {
        return true;
    }
}
