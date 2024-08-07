<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPushDeviceNotificationCommand extends Command
{

    protected $signature = 'test:push-device-notification {user_id}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $user = \App\User::find($this->argument('user_id'));
        $user->pushDeviceNotification(
            'Test push title',
            'Test push device notification',
            []
        );
    }
}
