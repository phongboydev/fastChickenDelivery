<?php

namespace App\GraphQL\Queries\Setting;

use Setting;

class GetSettingQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        logger(self::class . "@__invoke BEGIN", ['input' => $args]);
        $key = $args['key'];
        $setting = Setting::get($key);

        return $setting ?? "";
    }
}
