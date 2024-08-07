<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use App\Models\SocialSecurityAccount;

class GetSocialAccount
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

    /**
     * @param $workScheduleGroupdId
     * @param $clientEmployeeId
     *
     * @return \App\Models\Timesheet[]|array|\Illuminate\Support\Collection
     */
    public function handle($clientId)
    {

        $socialSecurityAccount = SocialSecurityAccount::select('*')->with('client')->where('client_id', $clientId)->authUserAccessible()->firstOrFail();

        Activity::create([
            'log_name' => 'default',
            'description' => 'getSocialAccount',
            'subject_type' => 'App\Models\SocialSecurityAccount',
            'subject_id' => $socialSecurityAccount->id,
            'causer_id' => Auth::user()->id,
            'causer_type' => 'App\User',
            'properties' => [
                'client_code' => $socialSecurityAccount->client->code
            ]
        ]);

        return [
            'username' => $socialSecurityAccount->username,
            'password' => $socialSecurityAccount->password
        ];
    }
}
