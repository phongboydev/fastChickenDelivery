<?php

namespace App\Providers;

use Gotenberg\Gotenberg;
use Gotenberg\Modules\Chromium;
use Gotenberg\Modules\LibreOffice;
use Illuminate\Support\ServiceProvider;

class GotenbergServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(Chromium::class, function($app) {
            return Gotenberg::chromium(config('vpo.gotenberg.url'));
        });

        $this->app->bind(LibreOffice::class, function($app) {
            return Gotenberg::libreOffice(config('vpo.gotenberg.url'));
        });
    }

    public function boot()
    {
        //
    }
}
