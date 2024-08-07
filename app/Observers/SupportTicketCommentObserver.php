<?php

namespace App\Observers;

use App\Models\SupportTicketComment;
use App\Notifications\SupportTicketEmailNotification;
use App\User;
use Illuminate\Support\Facades\Auth;

class SupportTicketCommentObserver
{
    /**
     * Handle the SupportTicketComment "created" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function creating(SupportTicketComment $supportTicketComment)
    {

    }

    /**
     * Handle the SupportTicketComment "created" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function created(SupportTicketComment $supportTicketComment)
    {
        $currentUser = Auth::user();
        $supportTicket = $supportTicketComment->supportTicket;
        $supportTicketComments = SupportTicketComment::where('support_ticket_id', $supportTicketComment->support_ticket_id)->with('user')->get();

        $supportTicketComments->each(function($item) use($currentUser, $supportTicket) {
                $user = $item->user;
                if($currentUser->id !== $user->id) {
                    $user->notify(new SupportTicketEmailNotification($supportTicket->client, $supportTicket->user->clientEmployee, $supportTicket, $user->isInternalUser() ? 'ask_question' : 'response'));
                }
            });

    }

    /**
     * Handle the SupportTicketComment "updated" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function updating(SupportTicketComment $supportTicketComment)
    {

    }

    /**
     * Handle the SupportTicketComment "updated" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function updated(SupportTicketComment $supportTicketComment)
    {

    }

    /**
     * Handle the SupportTicketComment "deleting" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function deleting(SupportTicketComment $supportTicketComment)
    {

    }

    /**
     * Handle the SupportTicketComment "deleted" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function deleted(SupportTicketComment $supportTicketComment)
    {

    }

    /**
     * Handle the SupportTicketComment "restored" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function restored(SupportTicketComment $supportTicketComment)
    {
        //
    }

    /**
     * Handle the SupportTicketComment "force deleted" event.
     *
     * @param SupportTicketComment $supportTicketComment
     * @return void
     */
    public function forceDeleted(SupportTicketComment $supportTicketComment)
    {

    }

}
