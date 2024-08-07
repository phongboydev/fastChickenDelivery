<?php

namespace App\Observers;

use App\Jobs\SendDocumentDeliveryNotificationJob;
use App\Models\Client;
use App\Models\ClientAppliedDocument;
use App\Support\WorktimeRegisterHelper;
use App\User;
use Illuminate\Support\Facades\Auth;

class ClientAppliedDocumentObserver
{

    public function creating(ClientAppliedDocument $clientAppliedDocument)
    {
        $client = Client::where('id', $clientAppliedDocument->client_id)->first();
        $item = ClientAppliedDocument::where("client_id", $clientAppliedDocument->client_id)
                                     ->with('client')
                                     ->latest()
                                     ->first();

        $code = $client->code . '-00000';

        if ($item) {
            $code = WorktimeRegisterHelper::generateNextID($item->code);
        }

        $clientAppliedDocument->code = $code;
    }

    public function created(ClientAppliedDocument $clientAppliedDocument)
    {
        /** @var User $user */
        $user = Auth::user();
        dispatch(new SendDocumentDeliveryNotificationJob($clientAppliedDocument, $user));
    }

    public function updated(ClientAppliedDocument $clientAppliedDocument)
    {

    }
}
