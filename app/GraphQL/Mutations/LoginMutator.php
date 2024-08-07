<?php

namespace App\GraphQL\Mutations;

use App\Support\Constant;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Auth\Events\PasswordReset;
use Joselfonseca\LighthouseGraphQLPassport\Exceptions\ValidationException;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Joselfonseca\LighthouseGraphQLPassport\GraphQL\Mutations\BaseAuthResolver;
use App\Models\Client;
use App\Models\Device;
use App\User;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\CustomException;
use App\Exceptions\HumanErrorException;
use App\Notifications\SendOtpNotification;
// use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Support\Facades\Password;
use Hash;
use PhpParser\Node\Stmt\TryCatch;
use GraphQL\Error\Error;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LoginMutator extends BaseAuthResolver
{

    // use SendsPasswordResetEmails;

    /**
     * Return a value for the field.
     *
     * @param null           $rootValue                                        Usually contains the result returned
     *                                                                         from the parent field. In this case, it
     *                                                                         is always `null`.
     * @param mixed[]        $args                                             The arguments that were passed into the
     *                                                                         field.
     * @param GraphQLContext $context                                          Arbitrary data that is shared between
     *                                                                         all fields of a single query.
     * @param ResolveInfo    $resolveInfo                                      Information about the query itself, such
     *                                                                         as the execution state, the field name,
     *                                                                         path to the field from the root, and
     *                                                                         more.
     *
     * @return mixed
     * @throws AuthenticationException
     */
    public function resolve($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        try {
            // Check if the username contains a hyphen ("-")
            if (strpos($args['username'], '-') !== false) {
                throw new CustomException('Tên đăng nhập hoặc mật khẩu không đúng. Hãy kiểm tra và nhập lại! Nội dung có thể nhầm lẫn giữa dấu "-" và dấu "_".', 'INVALID_USERNAME', 'DN001', [], "warning", "authentication");
            }

            $client = null;

            if (isset($args['client_code']) && $args['client_code'] != Constant::INTERNAL_DUMMY_CLIENT_CODE) {
                $client = Client::query()->where('code', $args['client_code'])->with('clientWorkflowSetting')->first();
                $username = $args['username'];
                if (!empty($client)) {
                    $username = sprintf("%s_%s", $client->id, $args['username']);
                }
            } else {
                $username =  Constant::INTERNAL_DUMMY_CLIENT_ID . '_' . $args['username'];
            }

            $loginPara = [
                'username' => $username,
                'password' => $args['password']
            ];

            $credentials = $this->buildCredentials($loginPara);
            $request = Request::create('oauth/token', 'POST', $credentials, [], [], [
                'HTTP_Accept' => 'application/json',
            ]);
            $response = app()->handle($request);
            $decodedResponse = json_decode($response->getContent(), true);

            if ($response->getStatusCode() != 200) {
                if ($decodedResponse['message'] === 'The provided authorization grant (e.g., authorization code, resource owner credentials) or refresh token is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.') {
                    throw new CustomException(__('Authentication exception'), __('Incorrect username or password'));
                }

                throw new CustomException('Tên đăng nhập hoặc mật khẩu không đúng. Hãy kiểm tra và nhập lại!', 'OAuthException', 'DN002', [], "warning", "authentication");
            }

            $this->validateAuthenticator($loginPara, $client, $args);

            return $decodedResponse;
        } catch (CustomException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Handle other exceptions
            throw new Error('An error occurred: ' . $e->getMessage(), null, null, [], null, null, [
                'reason' => $e->getMessage(),
                'code' => 'GENERIC_ERROR',
                'status' => 'error',
            ]);
        }
    }

    private function validateAuthenticator($loginPara, $client, $args)
    {
        if (isset($client->clientWorkflowSetting['enable_security_2fa']) && $client->clientWorkflowSetting['enable_security_2fa']) {
            $user = User::where('username', $loginPara['username'])->firstOrFail();

            if (($user->is_2fa_email_enabled && $user->is_2fa_authenticator_enabled) && !$args['device_id']) {
                throw new CustomException('Need verify OTP', 'Need verify OTP');
            }elseif($user->is_2fa_email_enabled && !$args['device_id']) {
                throw new CustomException('Need verify email OTP', 'Need verify email OTP');
            }elseif($user->is_2fa_authenticator_enabled && !$args['device_id']) {
                throw new CustomException('Need verify authenticator OTP', 'Need verify authenticator OTP');
            }elseif(!$user->is_2fa_email_enabled && !$user->is_2fa_authenticator_enabled) {
                return;
            }

            $google2fa = new Google2FA();

            $twofaSecurity = Device::where('user_id', $user->id)->where('device_id', $args['device_id'])->where('is_verifed', 1)->first();

            $oldTimestamp = $twofaSecurity->twofa_ts ? Carbon::parse($twofaSecurity->twofa_ts)->timestamp/30 : null;

            $timestamp = $google2fa->verifyKeyNewer($twofaSecurity->secret, $args['twofa_code'],  $oldTimestamp, 8);

            if ($timestamp !== false)
            {
                Device::where('user_id', $user->id)->where('device_id', $args['device_id'])->where('is_verifed', 1)->update(['twofa_ts' => date("Y-m-d H:i:s", time())]);
            } else {
                throw new CustomException(__('Incorrect authenticator'), __('Incorrect authenticator'));
            }
        }
    }

    public function verifyConfigAuthenticator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $client = Client::query()->where('code', $user->client['code'])->with('clientWorkflowSetting')->first();

        if (isset($client->clientWorkflowSetting['enable_security_2fa']) && $client->clientWorkflowSetting['enable_security_2fa']) {
            $google2fa = new Google2FA();

            $valid = $google2fa->verifyKey($args['twofa_code'], $args['otp'], 8);

            if (empty($valid)) {
                return false;
            }else{
                return true;
            }
        }

        return true;
    }

    public function registerDevice($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $deviceId   = $args['device_id'];
        $deviceName = isset($args['device_name']) ? $args['device_name'] : NULL;
        $firebaseId = isset($args['firebase_id']) ? $args['firebase_id'] : NULL;
        $isVerifed  = isset($args['is_verifed']) ? $args['is_verifed'] : false;

        $google2fa = new Google2FA();

        $google2fa_secret = '';

        if($isVerifed) {

            Device::where('user_id', $user->id)->where('device_id', $args['device_id'])->where('is_verifed', 1)->delete();

            if (!$args['is_enabled']) {

                if($deviceId == 'email') {
                    $user->is_2fa_email_enabled = false;
                    $user->save();
                }

                if($deviceId == 'authenticator') {
                    $user->is_2fa_authenticator_enabled = false;
                    $user->save();
                }

                return 'ok';
            }else{
                if($deviceId == 'email') {
                    $user->is_2fa_email_enabled = true;
                    $user->save();
                }

                if($deviceId == 'authenticator') {
                    $user->is_2fa_authenticator_enabled = true;
                    $user->save();
                }

                $tempDevice = Device::where('user_id', $user->id)->where('device_id', $deviceId)->where('is_verifed', 0)->first();

                if($tempDevice) {
                    Device::where('user_id', $user->id)->where('device_id', $deviceId)->where('is_verifed', 0)->update(['is_verifed' => 1]);
                }else{

                    $google2fa_secret = $google2fa_secret = $google2fa->generateSecretKey() ;

                    Device::create([
                        'user_id' => $user->id,
                        'device_id' => $args['device_id'],
                        'device_name' => $deviceName,
                        'firebase_id' => $firebaseId,
                        'category' => 'user',
                        'secret' => $google2fa_secret,
                        'is_verifed' => 1
                    ]);
                }
            }

        }else{

            $activeDevice = Device::where('user_id', $user->id)->where('device_id', $deviceId)->where('is_verifed', 1)->first();

            if(!$activeDevice) {

                Device::where('user_id', $user->id)->where('device_id', $deviceId)->where('is_verifed', 0)->delete();

                $google2fa_secret = $google2fa->generateSecretKey() ;

                Device::create([
                    'user_id' => $user->id,
                    'device_id' => $args['device_id'],
                    'device_name' => $deviceName,
                    'firebase_id' => $firebaseId,
                    'category' => 'user',
                    'secret' => $google2fa_secret,
                    'is_verifed' => 0
                ]);
            }else{
                $google2fa_secret = $activeDevice->secret;
            }

            if($deviceId == 'email') {

                $otp = $google2fa->getCurrentOtp($google2fa_secret);

                if (!$otp) {
                    throw new CustomException(__('Can not create Otp'), __('Can not create Otp'));
                }

                $user->notify(new SendOtpNotification($otp));

            }
        }

        return $google2fa_secret;
    }

    public function confirmLoginTypeAuthenticator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        try {
            $client = Client::query()->where('code', $args['client_code'])->with('clientWorkflowSetting')->firstOrFail();

            $username = sprintf("%s_%s", $client->id, $args['username']);

            $credentials = $this->buildCredentials([
                'username' => $username,
                'password' => $args['password']
            ]);

            $request = Request::create('oauth/token', 'POST', $credentials, [], [], [
                'HTTP_Accept' => 'application/json',
            ]);
            $response = app()->handle($request);
            if ($response->getStatusCode() != 200) {
                throw new CustomException(__('Authentication exception'), __('Incorrect username or password'));
            }

            $user = User::where('username', $username)->firstOrFail();

            if (isset($client->clientWorkflowSetting['enable_security_2fa']) && $client->clientWorkflowSetting['enable_security_2fa']) {

                if ($user->is_2fa_email_enabled) {

                    $twofaSecurity = Device::where('user_id', $user->id)->where('device_id', 'email')->where('is_verifed', 1)->first();

                    if (!$twofaSecurity) return false;

                    $google2fa = new Google2FA();

                    $otp = $google2fa->getCurrentOtp($twofaSecurity->secret);

                    if (!$otp) {
                        return false;
                    }

                    $user->notify(new SendOtpNotification($otp));
                }else {
                    throw new CustomException(__('is_2fa_email_not_enabled'), __('is_2fa_email_not_enabled'));
                }

                return true;
            }

            return false;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Generate 2FA secret key
     */
    public function generate2faSecretCode($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $google2fa = new Google2FA();

        if(isset($args['device_id']) && $args['device_id'] == 'email') {
            $twofaSecurity = Device::where('user_id', $user->id)->where('device_id', 'email')->first();

            $secret = !empty($twofaSecurity) ? $twofaSecurity->secret : $google2fa->generateSecretKey();

            $otp = $google2fa->getCurrentOtp($secret);

            if (!$otp) {
                throw new CustomException(__('Can not create Otp'), __('Can not create Otp'));
            }

            $user->notify(new SendOtpNotification($otp));

            return $secret;
        }else if(isset($args['device_id']) && $args['device_id'] == 'authenticator') {
            $twofaSecurity = Device::where('user_id', $user->id)->where('device_id', 'authenticator')->first();

            return !empty($twofaSecurity) ? $twofaSecurity->secret : $google2fa->generateSecretKey();
        }else{

            $google2fa_secret = $google2fa->generateSecretKey();

            return $google2fa_secret;
        }
    }

    /**
     * Enable 2FA
     */
    public function enable2fa($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());
        $secret = $args['secretKey'];
        $valid = $google2fa->verifyKey($user->google2fa_secret, $secret);
        if ($valid) {
            $user->google2fa_enable = 1;
            $user->save();

            $response = [
                'status' => 1,
                'mess'   => "2FA is enabled successfully."
            ];
            return json_encode($response);
        } else {
            $response = [
                'status' => 0,
                'mess'   => "Invalid verification Code, Please try again."
            ];
            return json_encode($response);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable2fa($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        if (!(Hash::check($args['password'], Auth::user()->password))) {
            // The passwords matches
            throw new CustomException(
                'Your password does not matches with your account password. Please try again.',
                'ValidationException'
            );
        }

        $user = Auth::user();
        $user->google2fa_enable = 0;
        $user->save();

        $response = [
            'status' => 1,
            'mess'   => "2FA is now disabled."
        ];
        return json_encode($response);
    }

    /**
     * Get 2FA SecretKey
     */
    public function get2faSecretKey($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::user();

        $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());
        $secretKey = $user->google2fa_secret;

        if (is_null($secretKey) || empty($secretKey) || ($user->google2fa_enable == 0)) {
            throw new CustomException(
                'SecretKey is empty OR 2FA is now disabled.',
                'ValidationException'
            );
        }

        $data = [
            'user'      => $user,
            'secretKey' => $secretKey
        ];

        return json_encode($data);
    }

    public function broker()
    {
        return Password::broker('users');
    }

    public function customerForgotPassword($root, array $args)
    {
        $user = User::findByVPOCredentials($args['username'], $args['client_code']);

        if (empty($user)) {
            return [
                'status'  => 'INVALID_CLIENT_CODE_USERNAME',
                'message' => 'Invalid client code or username'
            ];
        }

        $response = $this->broker()->sendResetLink([
            'id' => $user->id,
            'username' => $user->username,
        ]);

        if ($response === Password::RESET_LINK_SENT) {
            return [
                'status'  => 'EMAIL_SENT',
                'message' => trans($response),
            ];
        }

        return [
            'status'  => 'EMAIL_NOT_SENT',
            'message' => trans($response),
        ];
    }

    /**
     * @param null                 $_
     * @param array<string, mixed> $args
     *
     * @throws ValidationException
     */
    public function resetPassword($_, array $args): array
    {
        $user = User::findByVPOCredentials(
            $args["username"],
            $args["client_code"],
            false
        );

        if (!$user) {
            throw new ValidationException([], __('An error has occurred while resetting the password'));
        }

        $response = $this->broker()->reset([
            "password" => $args["password"],
            "password_confirmation" => $args["password_confirmation"],
            "token" => $args["token"],
            "email" => $user->email,
            "id" => $user->id,
        ], function ($user, $password) {
            $user->password = Hash::make($password);
            $user->save();
            event(new PasswordReset($user));
        });

        if ($response === Password::PASSWORD_RESET) {
            return [
                'status' => 'PASSWORD_UPDATED',
                'message' => __($response),
            ];
        }

        throw new ValidationException([
            'token' => __($response),
        ], __('An error has occurred while resetting the password'));
    }
}
