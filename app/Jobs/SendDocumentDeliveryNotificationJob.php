<?php

namespace App\Jobs;

use App\Mail\DocumentDeliveryEmail;
use App\Models\ClientAppliedDocument;
use App\Models\Comment;
use App\Notifications\AppliedDocumentNotification;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;

class SendDocumentDeliveryNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ClientAppliedDocument $clientAppliedDocument;
    protected $comment;
    protected User $sender;

    const IGNORE_USER_ID_LIST = [
        'a45e1696-25e5-49db-a59d-aafb18978eb7',
        'd595d5c4-58ae-4550-a994-caa012f27c77',
        'adb949dc-95a4-4613-8c23-827a331f9a60',
        'cfb9e1ab-bfe1-486c-a786-6cb9e3dd7499',
        'f2c03eb2-3c83-4d24-8e70-ceac4730ac82'
    ];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(ClientAppliedDocument $clientAppliedDocument, User $sender, $comment = null)
    {
        $this->clientAppliedDocument = $clientAppliedDocument;
        $this->sender = $sender;
        $this->comment = $comment;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->comment) {
                $this->commentNotification();
            } else {
                $this->creatingNotification();
            }
        } catch (\Exception $exception) {
            logger()->error("DocumentDeliveryEmail error: " . $exception->getMessage());
            $this->addLog("Client Document Id: {$this->clientAppliedDocument->id}; Type: {$this->type}; Message error: {$exception->getMessage()}");
        }

    }

    private function creatingNotification()
    {
        $notification = new AppliedDocumentNotification($this->clientAppliedDocument);
        $mailToUsers = [];
        $client = $this->clientAppliedDocument->client;
        if ($this->sender->isInternalUser()) {
            //send mail to user list which are picked by internal user on screen
            foreach($this->clientAppliedDocument->clientNotificationUsers as $userNotification) {
                $userNotification->notify($notification);
                if ($userNotification->is_email_notification) {
                    $mailToUsers[] = $userNotification->email;
                }
            }
            $callback = "createdByInternal";
        } else {
            //send mail to internal users which are assigned to this client.
            $client->assignedInternalEmployees()
                ->chunkById(100, function ($internalEmployees) use ($notification, &$mailToUsers) {
                    foreach ($internalEmployees as $internalEmployee) {
                        /** @var User $user */
                        $user = $internalEmployee->user;
                        if (!in_array($user->id, self::IGNORE_USER_ID_LIST) && $user->is_email_notification) {
                            $user->notify($notification);
                            $mailToUsers[] = $user->email;
                        }
                    }
                });
            $callback = "createdByCustomer";
        }
        if(!empty($mailToUsers)) {
            $ccList = $this->clientAppliedDocument->ccClientEmails->pluck('email')->toArray();
            Mail::to($mailToUsers)->cc($ccList)->send(new DocumentDeliveryEmail($this->clientAppliedDocument, $this->sender, $callback));
        }
    }

    private function commentNotification()
    {
        $notification = new AppliedDocumentNotification($this->clientAppliedDocument);
        $mailToUsers = [];
        $client = $this->clientAppliedDocument->client;
        $documentCreatedBy = $this->clientAppliedDocument->user;

        /**
         * If the sender and the document creator is not the same (internal/customer)
         * Sending notification for the document creator
         * */
        if ($this->sender->isInternalUser() != $documentCreatedBy->isInternalUser()) {
            $documentCreatedBy->notify($notification);
            $mailToUsers[] = $documentCreatedBy->email;
            $callback = $this->sender->isInternalUser() ? "commentByInternal" : "commentByCustomer";
        } else {
            /**
             * Case the sender and the document creator are the same (internal/customer)
             * */
            if ($this->sender->isInternalUser()) {
                //send mail to user list which are picked by internal user on screen
                foreach($this->clientAppliedDocument->clientNotificationUsers as $userNotification) {
                    $userNotification->notify($notification);
                    if ($userNotification->is_email_notification) {
                        $mailToUsers[] = $userNotification->email;
                    }
                }
                $callback = "commentByInternal";
            } else {
                //send mail to users which are assigned to manage this client
                $client->assignedInternalEmployees()
                    ->chunkById(100, function ($internalEmployees) use ($notification, &$mailToUsers) {
                        foreach ($internalEmployees as $internalEmployee) {
                            /** @var User $user */
                            $user = $internalEmployee->user;
                            if (!in_array($user->id, self::IGNORE_USER_ID_LIST) && $user->is_email_notification) {
                                $user->notify($notification);
                                $mailToUsers[] = $user->email;
                            }
                        }
                    });
                $callback = "commentByCustomer";
            }
        }

        if(!empty($mailToUsers)) {
            $ccList = $this->clientAppliedDocument->ccClientEmails->pluck('email')->toArray();
            Mail::to($mailToUsers)->cc($ccList)->send(new DocumentDeliveryEmail($this->clientAppliedDocument, $this->sender, $callback, $this->comment));
        }
    }

    private function addLog($log)
    {
        $this->clientAppliedDocument->client->addLog('document_delivery', $log);
    }
}
