<?php

namespace App\GraphQL\Mutations\Setting;

use Setting;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\CustomException;
use App\Support\Constant;

class SetSettingMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $user = Auth::user();
        $role = $user->getRole();

        if ( !$user->isInternalUser() || $role != Constant::ROLE_INTERNAL_DIRECTOR ) {
            throw new CustomException(
                'You have not permission to update settings',
                'ValidationException'
            );
        }

        logger(self::class . "@__invoke BEGIN", ['input' => $args]);
        $key = $args['key'];
        $value = $args['value'];
        Setting::set($key, $value);

        return $value;
    }

    public function updateSettings($root, array $args) {
        $user = Auth::user();
        $role = $user->getRole();

        if ( $user->isInternalUser() && $role == Constant::ROLE_INTERNAL_DIRECTOR ) {
            $settings = $args['settings'];
            foreach (json_decode($settings) as $key => $setting) {
                $key = $setting->key;
                $value = $setting->value;
                Setting::set($key, $value);
            }
            return "done";
        } else {
            throw new CustomException(
                'You have not permission to update settings',
                'ValidationException'
            );
        }
    }
}
