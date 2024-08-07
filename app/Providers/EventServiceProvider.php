<?php

namespace App\Providers;

use App\Events\CalculateTimeSheetShiftMappingEvent;
use App\Events\CalculationSheetCalculatedEvent;
use App\Events\CalculationSheetReadyEvent;
use App\Events\DataImportCreatedEvent;

use App\Listeners\CalculateTimeSheetShiftMappingListener;
use App\Listeners\CalculationSheetReadyListener;
use App\Listeners\ProcessPayrollAccountantListener;
use App\Listeners\DataImportCreatedListener;
use App\Listeners\CalculationSheetDoubleVariableListener;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        CalculationSheetCalculatedEvent::class => [
            ProcessPayrollAccountantListener::class,
            CalculationSheetDoubleVariableListener::class
        ],
        CalculationSheetReadyEvent::class => [
            CalculationSheetReadyListener::class,
        ],
        DataImportCreatedEvent::class => [
            DataImportCreatedListener::class,
        ],
        CalculateTimeSheetShiftMappingEvent::class => [
            CalculateTimeSheetShiftMappingListener::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
