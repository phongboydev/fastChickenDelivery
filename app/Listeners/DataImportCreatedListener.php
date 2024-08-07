<?php

namespace App\Listeners;

use App\Events\DataImportCreatedEvent;
use App\Models\DataImportHistory;

class DataImportCreatedListener
{
    protected $content;

    public function __construct()
    {
        
    }

    public function handle(DataImportCreatedEvent $event)
    {
        $content = $event->content;

        $importDataHistory = DataImportHistory::create([
            'type' => $content['type'],
            'client_id' => $content['client_id'],
            'user_id' => $content['user_id'],
        ]);

        $importDataHistory->addMediaFromDisk($content['file'], 'local')
        ->toMediaCollection('DataImportHistory', 'minio');
    }
}
