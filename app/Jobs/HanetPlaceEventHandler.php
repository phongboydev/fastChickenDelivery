<?php

namespace App\Jobs;

use App\DTO\HanetPlaceEvent;
use App\Models\HanetDevice;
use App\Models\HanetPlace;
use App\Models\HanetPlacePerson;
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

class HanetPlaceEventHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HanetPlaceEvent $event;

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
                return $this->addPlace($event);
            case 'update':
                return $this->updatePlace($event);
            case 'delete':
                return $this->deletePlace($event);
        }
    }

    private function addPlace($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop addPlace. Can not find client from keycode ' . $event->keycode);
            return;
        }

        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        HanetPlace::insert(array(
            array(
                'id' => Str::uuid(),
                'client_id' => $client_id,
                'name' => $event->place_name,
                'hanet_place_id' => $event->place_id,
                'created_at' => $datetime,
                'updated_at' => $datetime
            )
        ));
    }

    private function updatePlace($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop updatePlace. Can not find client from keycode ' . $event->keycode);
            return;
        }

        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        HanetPlace::where('hanet_place_id', $event->place_id)
        ->update([
            'name' => $event->place_name,
            'updated_at' => $datetime,
        ]);
    }

    private function deletePlace($event) {
        $client_id = $this->get_client($event->keycode);

        if (is_null($client_id) || $client_id == null) {
            logger()->debug('Stop deletePlace. Can not find client from keycode ' . $event->keycode);
            return;
        }

        $places = HanetPlace::select('*')->where('hanet_place_id', $event->place_id)->get()->toArray();

        $place_id_list = array_map(function($item) {
            return $item['id'];
        }, $places);

        HanetPlacePerson::whereIn('hanet_place_id', $place_id_list)->delete();

        HanetPlace::where('hanet_place_id', $event->place_id)->delete();
       
    }

    private function get_client($keycode) {
        $setting = HanetSetting::select('client_id')
        ->where('partner_token', $keycode)
        ->first();
    
    
        if ($setting == null) return null;

        return $setting->client_id;
    }
}
