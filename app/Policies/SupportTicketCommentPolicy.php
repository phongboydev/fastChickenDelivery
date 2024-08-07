<?php

namespace App\Policies;

use App\Models\SupportTicketComment;
use App\User;
use App\Models\SupportTicket;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SupportTicketCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any support ticket comments.
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
     * Determine whether the user can view the support ticket comment.
     *
     * @param User $user
     * @param SupportTicketComment $supportTicketComment
     *
     * @return mixed
     */
    public function view(User $user, SupportTicketComment $supportTicketComment)
    {
        //
    }

    /**
     * Determine whether the user can create support ticket comments.
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
                    $supportTicket = SupportTicket::query()->where('id', $injected['support_ticket_id'])->first();

                    if (!$supportTicket || $user->id != $supportTicket->user_id) {
                        return false;
                    }
                    if ($user->id != $injected['user_comment_id']) {
                        return false;
                    }
                    return true;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    $supportTicket = SupportTicket::query()->where('id', $injected['support_ticket_id'])->first();
                    if (!$supportTicket || !$user->iGlocalEmployee->isAssignedFor($supportTicket->client_id)) {
                        return false;
                    }
                    return true;
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return true;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can update the support ticket comment.
     *
     * @param User $user
     * @param SupportTicketComment $supportTicketComment
     *
     * @return mixed
     */
    public function update(User $user, SupportTicketComment $supportTicketComment)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    $supportTicket = $supportTicketComment->supportTicket;
                    if (!$supportTicket || $user->id != $supportTicket->user_id) {
                        return false;
                    }
                    if ($user->id != $supportTicketComment->user_comment_id) {
                        return false;
                    }
                    return true;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    $supportTicket = $supportTicketComment->supportTicket;
                    if (!$supportTicket || !$user->iGlocalEmployee->isAssignedFor($supportTicket->client_id)) {
                        return false;
                    }
                    return true;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the support ticket comment.
     *
     * @param User $user
     * @param SupportTicketComment $supportTicketComment
     *
     * @return mixed
     */
    public function delete(User $user, SupportTicketComment $supportTicketComment)
    {
        return false;
    }

    /**
     * Determine whether the user can restore the support ticket comment.
     *
     * @param User $user
     * @param SupportTicketComment $supportTicketComment
     *
     * @return mixed
     */
    public function restore(User $user, SupportTicketComment $supportTicketComment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the support ticket comment.
     *
     * @param User $user
     * @param SupportTicketComment $supportTicketComment
     *
     * @return mixed
     */
    public function forceDelete(User $user, SupportTicketComment $supportTicketComment)
    {
        //
    }

    public function upload(User $user, SupportTicketComment $supportTicketComment): bool
    {
        return true;
    }
}
