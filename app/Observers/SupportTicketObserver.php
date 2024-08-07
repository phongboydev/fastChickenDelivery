<?php

namespace App\Observers;


use App\Models\SupportTicket;
use App\User;
use App\Notifications\SupportTicketEmailNotification;
use Illuminate\Support\Facades\Auth;

class SupportTicketObserver
{
    /**
     * Handle the support ticket "created" event.
     *
     * @param SupportTicket $supportTicket
     * @return void
     */
    public function created(SupportTicket $supportTicket)
    {
        $currentUser = Auth::user();
        if (!$currentUser->isInternalUser()) {
            $assignmentUsers = User::systemNotifiable()->with('iGlocalEmployee')->get();
            $assignmentUsers->each(function($user) use ($supportTicket) {
                if (isset($user->iGlocalEmployee) && $user->iGlocalEmployee->isAssignedFor($supportTicket->client->id)) {
                    try{
                        $user->notify(new SupportTicketEmailNotification($supportTicket->client, $supportTicket->user->clientEmployee, $supportTicket, 'new'));
                    }catch(\Exception $e){
                        logger()->warning( "SupportTicket can not sent email", [
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

            });

        }
    }

    /**
     * Handle the support ticket "updated" event.
     *
     * @param SupportTicket $supportTicket
     * @return void
     */
    public function updated(SupportTicket $supportTicket)
    {

        $currentUser = Auth::user();

        if ($currentUser->isInternalUser()) {
            $supportTicket->user->notify(new SupportTicketEmailNotification($supportTicket->client, $supportTicket->user->clientEmployee, $supportTicket, 'response'));
        }else if($supportTicket->status != 'new' ){
            $assignmentUsers = User::systemNotifiable()->with('iGlocalEmployee')->get();
            $assignmentUsers->each(function($user) use ($supportTicket) {
                if (isset($user->iGlocalEmployee) && $user->iGlocalEmployee->isAssignedFor($supportTicket->client->id)) {
                    $user->notify(new SupportTicketEmailNotification($supportTicket->client, $supportTicket->user->clientEmployee, $supportTicket, 'customer_updated'));
                }
            });
        }
    }

    /**
     * Handle the support ticket "deleted" event.
     *
     * @param SupportTicket $supportTicket
     * @return void
     */
    public function deleted(SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Handle the support ticket "restored" event.
     *
     * @param SupportTicket $supportTicket
     * @return void
     */
    public function restored(SupportTicket $supportTicket)
    {
        //
    }

    /**
     * Handle the support ticket "force deleted" event.
     *
     * @param SupportTicket $supportTicket
     * @return void
     */
    public function forceDeleted(SupportTicket $supportTicket)
    {
        //
    }
}
