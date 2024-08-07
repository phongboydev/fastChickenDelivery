<?php

namespace App\GraphQL\Queries;

use App\Models\SocialSecurityAccount;

class GetSocialAccountState
{

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $clientId = $args['client_id'];
        return $this->handle($clientId);
    }

    public function handle($clientId)
    {        
        $socialSecurityAccount = SocialSecurityAccount::select('state')->where('client_id', $clientId)->authUserAccessible()->firstOrFail();

        return $socialSecurityAccount->state;
    }
}
