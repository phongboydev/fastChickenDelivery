<?php

namespace App\Observers;

use App\Support\Constant;
use App\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Approve;
use App\Models\ApproveFlowUser;

class UserObserver
{
    /**
     * Handle the user "creating" event.
     *
     * @param User $user
     *
     * @return void
     */
    public function creating(User $user)
    {
        if ($user->is_internal) {
            $user->client_id = Constant::INTERNAL_DUMMY_CLIENT_ID;
        } else {
            $user->is_internal = 0;
        }

        if (!empty($user->username)) {
            if (!Str::startsWith($user->username, $user->client_id)) {
                $user->username = $user->client_id . '_' . $user->username;
            }
        }

        $user->timezone_name = $user->timezone_name ?? Constant::TIMESHEET_TIMEZONE;
    }

    public function created(User $user)
    {
        $user->refreshPermissions();
    }

    /**
     * Handle the user "upaating" event.
     *
     * @param  User  $user
     *
     * @return void
     */
    public function updating(User $user)
    {
        if ($user->is_internal) {
            $user->client_id = Constant::INTERNAL_DUMMY_CLIENT_ID;
            $username = Str::replaceFirst($user->client_id . '_', '', $user->username);
            $user->username = Constant::INTERNAL_DUMMY_CLIENT_ID . '_' . $username;
        } else {
            $user->is_internal = 0;
            $username = Str::replaceFirst($user->client_id . '_', '', $user->username);
            $user->username = $user->client_id . '_' . $username;
        }

        if (!$user->is_active) {
            DB::table('oauth_access_tokens')->where('user_id', $user->id)->delete();
        }

        if ($user->google2fa_enable && !$user->google2fa_secret) {
            $google2fa = app('pragmarx.google2fa');
            $user->google2fa_secret = $google2fa->generateSecretKey();
        }
    }

    public function deleted(User $user)
    {
        Approve::where('creator_id', $user->id)->delete();
        ApproveFlowUser::where('user_id', $user->id)->delete();
    }
}
