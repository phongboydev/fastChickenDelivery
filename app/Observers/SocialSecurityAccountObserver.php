<?php

namespace App\Observers;

use App\Models\SocialSecurityAccount;
use Illuminate\Support\Facades\Auth;

class SocialSecurityAccountObserver
{

    public function creating(SocialSecurityAccount $account)
    {
        $account = $this->encryptData($account);
    }

    public function updating(SocialSecurityAccount $account)
    {
        $account = $this->encryptData($account);
    }

    protected function encryptData(SocialSecurityAccount $account)
    {
        $newEncrypter = new \Illuminate\Encryption\Encrypter( config( 'app.social_account_key' ), config('app.social_account_cipher') );

        $account->creator_id  = Auth::user()->id;
        $account->state    = md5($account->username . $account->password);
        $account->username = $newEncrypter->encrypt($account->username);
        $account->password = $newEncrypter->encrypt($account->password);
        
        return $account;
    }
}
