<?php

namespace App\GraphQL\Queries;

use App\Models\Client;
use App\User;

class AvailablePasswordResetMethodsQuery
{
    /**
     * @param  null  $_
     * @param  array{
     *     client_code:string,
     *     username:string,
     * }  $args
     */
    public function __invoke($_, array $args)
    {
        $methods = ['email'];

        $clientCode = $args['client_code'];
        $username = $args['username'];

        $user = User::findByVPOCredentials($username, $clientCode);
        if (!$user) {
            return $methods;
        }

        $clientEmployee = $user->clientEmployee;
        $client = Client::where('code', $clientCode)->first();
        if ($client && $client->clientWorkflowSetting &&
            $client->clientWorkflowSetting->sms_available &&
            $clientEmployee &&
            !empty($clientEmployee->contact_phone_number)) {
            $methods[] = 'sms';
        }
        return $methods;
    }
}
