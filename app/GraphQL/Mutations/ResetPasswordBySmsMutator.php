<?php

namespace App\GraphQL\Mutations;

use App\User;
use Kreait\Laravel\Firebase\Facades\Firebase;

class ResetPasswordBySmsMutator
{
    /**
     * @param  null  $_
     * @param  array{client_code: string, username: string, firebase_cookie: string}  $args
     */
    public function __invoke($_, array $args)
    {
        $clientCode = $args['client_code'];
        $username = $args['username'];
        $idToken = $args['id_token'];
        $newPassword = $args['new_password'] ?? null;

        logger($args);
        $user = User::findByVPOCredentials($username, $clientCode);
        if (!$user || !$user->clientEmployee) {
            return [
                'status'  => 'INVALID_USER',
                'message' => 'Invalid user'
            ];
        }

        $ce = $user->clientEmployee;
        $phoneNumber = $ce->contact_phone_number;
        // auto add country code +84 if not exist
        if ($phoneNumber[0] !== '+') {
            $phoneNumber = '+84' . substr($phoneNumber, 1);
        }

        try {
            $token = Firebase::auth()->verifyIdToken($idToken);
        } catch (\Exception $e) {
            // possibly wrong firebase configuration
            logger($e->getMessage());
            return [
                'status'  => 'INVALID_TOKEN',
                'message' => 'Invalid token'
            ];
        }

        logger("Employee phone: " . $phoneNumber);
        $claimPhoneNumber = $token->claims()->get('phone_number');
        logger("Claim phone: " . $claimPhoneNumber);

        if ($phoneNumber !== $claimPhoneNumber) {
            return [
                'status'  => 'INVALID_PHONE_NUMBER',
                'message' => 'Phone number mismatched'
            ];
        }

        if (!$newPassword) {
            return [
                'status'  => 'OK',
                'message' => 'Phone number matched, send new password to reset'
            ];
        }

        $user->password = bcrypt($newPassword);
        $user->save();
        return [
            'status'  => 'OK',
            'message' => 'Password reset successfully'
        ];
    }
}
