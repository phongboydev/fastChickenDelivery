<?php

namespace App\Jobs;

use App\DTO\HanetDeviceEvent;
use App\Models\HanetDevice;
use App\Models\HanetSetting;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HanetDeviceEventHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HanetDeviceEvent $event;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($event)
    {  
        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $event = $this->event;
        
        switch ($event->action_type) {
            case 'add':
                return $this->addDevice($event);
            case 'update':
                return $this->updateDevice($event);
            case 'delete':
                return $this->deleteDevice($event);
        }
    }

    private function addDevice($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop addDevice. Can not find client from keycode ' . $event->keycode);
            return;
        }

        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        HanetDevice::insert(array(
            array(
                'id' => Str::uuid(),
                'client_id' => $client_id,
                'name' => $event->device_name,
                'device_id' => $event->device_id,
                'place_id' => $event->place_id,  
                'place_name' => $event->place_name,
                'created_at' => $datetime,
                'updated_at' => $datetime
            )
        ));
    }

    private function updateDevice($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop updateDevice. Can not find client from keycode ' . $event->keycode);
            return;
        }

        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        HanetDevice::where('device_id', $event->device_id)
        ->update([
            'name' => $event->device_name,
            'place_id' => $event->place_id,
            'place_name' => $event->place_name,
            'updated_at' => $datetime,
        ]);
    }

    private function deleteDevice($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop deleteDevice. Can not find client from keycode ' . $event->keycode);
            return;
        }

        HanetDevice::where('device_id', $event->device_id)->delete();
       
    }

    private function get_client($keycode) {
        $setting = HanetSetting::select('client_id')
            ->where('partner_token', $keycode)
            ->first();
        
        
        if ($setting == null) return null;

        return $setting->client_id;
    }
}
