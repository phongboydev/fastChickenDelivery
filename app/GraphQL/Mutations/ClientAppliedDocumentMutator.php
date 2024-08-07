<?php

namespace App\GraphQL\Mutations;


use App\Models\ClientAppliedDocument;
use App\Support\Constant;
use Illuminate\Support\Facades\Auth;

class ClientAppliedDocumentMutator
{
    public function createClientAppliedDocument($root, array $args)
    {
        $clientAppliedDocument = ClientAppliedDocument::create([
            'client_id' => $args['client_id'],
            'document_type' => $args['document_type'],
            'status' => Constant::NEW_STATUS,
            'user_id' => Auth::user()->id,
            'description' => $args['description'] ?? null,
            'detail' => $args['detail'] ?? null,
        ]);

        if (isset($args['to_client_user_ids'])) {
            $clientAppliedDocument->clientNotificationUsers()->attach($args['to_client_user_ids']);
        }

        if (isset($args['cc_client_email_ids'])) {
            $clientAppliedDocument->ccClientEmails()->attach($args['cc_client_email_ids']);
        }

        return $clientAppliedDocument;
    }
}
