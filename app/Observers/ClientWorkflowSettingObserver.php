<?php

namespace App\Observers;

use App\Jobs\SendHeadCountChangeEmail;
use App\Models\Client;
use App\Models\ClientHeadCountHistory;
use App\Support\Constant;
use App\User;
use App\Models\ClientWorkflowSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ClientWorkflowSettingObserver
{
    public function updated(ClientWorkflowSetting $clientWorkflowSetting)
    {
        if($clientWorkflowSetting->enable_security_2fa == false && $clientWorkflowSetting->enable_security_2fa != $clientWorkflowSetting->getOriginal("enable_security_2fa")){
            User::where('client_id', $clientWorkflowSetting->client_id)
            ->chunkById(100, function($users) {
                foreach($users as $user) {
                    if($user['google2fa_enable'] != 0) {
                        $user->update(['google2fa_enable' => 0]);
                    }
                }
            }, 'id');
        }

        if($clientWorkflowSetting->enable_auto_approve == false && $clientWorkflowSetting->enable_auto_approve != $clientWorkflowSetting->getOriginal("enable_auto_approve")){
            User::where('client_id', $clientWorkflowSetting->client_id)
            ->chunkById(100, function($users) {
                foreach($users as $user) {
                    if($user['auto_approve'] != 0) {
                        $user->update(['auto_approve' => 0]);
                    }
                }
            }, 'id');
        }

        if ($clientWorkflowSetting->wasChanged('client_employee_limit')) {
            /** @var User $user */
            $user = Auth::user();
            if (!$user->isInternalUser()) {
                ClientHeadCountHistory::create([
                    'client_id' => $user->client_id,
                    'old_number' => $clientWorkflowSetting->getOriginal('client_employee_limit'),
                    'new_number' => $clientWorkflowSetting->client_employee_limit,
                    'updated_by' => $user->id,
                ]);

                $params = [
                    'old_number' => $clientWorkflowSetting->getOriginal('client_employee_limit'),
                    'new_number' => $clientWorkflowSetting->client_employee_limit,
                    'changed_at' => Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateTimeString(),
                ];
                dispatch(new SendHeadCountChangeEmail($user, $user->client, $params));
            }
        }
    }
}
