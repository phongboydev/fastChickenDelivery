<?php

namespace App\Observers;

use App\Models\ActionLog;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;

class ActionLogObserver
{

    public function creating(ActionLog $actionLog)
    {
        $user = Auth::user();
        if(!empty($user)){
            $clientEmployee = $user->clientEmployee;
            $client = Client::where('id', $user->client_id)->first();
            $actionLog->user_id = $user->id;
            $actionLog->client_employee_code = !empty($clientEmployee->code) ? $clientEmployee->code : "";
            $actionLog->client_code = !empty($client->code) ? $client->code : "";
            $actionLog->username = str_replace($user->client_id . '_', "", $user->username);
            $actionLog->client_id = $user->client_id;
        } else {
            $actionLog->user_id = null;
            $actionLog->client_employee_code = "";
            $actionLog->client_code = "";
            $actionLog->username = "";
            $actionLog->client_id = null;
        }

        return $actionLog;
    }
}
