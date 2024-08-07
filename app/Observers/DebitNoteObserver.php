<?php

namespace App\Observers;

use App\Support\Constant;
use App\Models\DebitNote;
use App\User;
use App\Models\ClientEmployee;
use App\Notifications\DebitNoteNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;

class DebitNoteObserver
{
    /**
     * Handle the support ticket "created" event.
     *
     * @param  \App\DebitNote  $debitNote
     * @return void
     */
    public function created(DebitNote $debitNote)
    {
       
        $clientEmployees = ClientEmployee::query()
                            ->where('role', Constant::ROLE_CLIENT_ACCOUNTANT)
                            ->where('client_id', $debitNote->client_id)
                            ->get();

        $clientEmployees->each(function($clientEmployee) use ($debitNote) {

            $user = User::find($clientEmployee->user_id);

            if( !empty($user) ) {
                $user->notify(new DebitNoteNotification($debitNote));
            }
        });
    }

    /**
     * Handle the support ticket "updated" event.
     *
     * @param  \App\SupportTicket  $supportTicket
     * @return void
     */
    public function updated(SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Handle the support ticket "deleted" event.
     *
     * @param  \App\SupportTicket  $supportTicket
     * @return void
     */
    public function deleted(SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Handle the support ticket "restored" event.
     *
     * @param  \App\SupportTicket  $supportTicket
     * @return void
     */
    public function restored(SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Handle the support ticket "force deleted" event.
     *
     * @param  \App\SupportTicket  $supportTicket
     * @return void
     */
    public function forceDeleted(SupportTicket $supportTicket)
    {
        //
    }
}
