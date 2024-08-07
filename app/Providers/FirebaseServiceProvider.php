<?php

namespace App\Providers;

use App\Firebase\FirebaseProjectManager;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class FirebaseServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->singleton(
            \Kreait\Laravel\Firebase\FirebaseProjectManager::class,
            static fn (Container $app) => new FirebaseProjectManager($app)
        );
    }

    public function boot(): void
    {
    }
}
