<?php

namespace App\Jobs;

use App\Models\HanetDevice;
use App\Models\HanetPlace;
use App\Models\HanetSetting;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncHanetPlace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $place;
    protected $type;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($place, $type)
    {
        $this->place = $place;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $place = $this->place;
        $client_id = $place['client_id'];

        $setting = HanetSetting::select('token')
        ->where('client_id', $client_id)
        ->first();

        if (is_null($setting) || is_null($setting->token)) {
            logger()->error("sync_hanet_place error: access_token is empty");
            return null;
        }

        $access_token = $setting->token;

        if ($this->type == 'create') {
            $name = $place['name'];
            $address = $place['address'];

            $hanet_place = $this->createHanetPlace($access_token, $name, $address);
            if (!is_null($hanet_place)) {
                $this->updateHanetPlaceId($place, $hanet_place);
            }
        }

        if ($this->type == 'delete') {
            $hanet_place_id = $place['hanet_place_id'];

            if (is_null($hanet_place_id) || $hanet_place_id == '') {
                logger()->debug('Stop delete hanet place. Place ID is empty');
                return;
            }

            $camera_list = $this->getHanetPlaceCamera($access_token, $hanet_place_id);

            if (empty($camera_list)) {
                logger()->debug('Stop delete hanet camera place. Place has no camera');

                // ** Call HANET API to delete places
                $this->deleteHanetPlace($access_token, $hanet_place_id);

                return;
            }

            $camera_ids = array_map(function($item) {
                return $item->deviceID;
            }, $camera_list);


            // ** Delete devices in database
            HanetDevice::whereIn('device_id', $camera_ids)->delete();

            // ** Call HANET API to delete places
            $this->deleteHanetPlace($access_token, $hanet_place_id);
        }

    }

    private function createHanetPlace($access_token, $name, $address) {
        $end_point = config("hanet.partner_url");

        try {
            $response = Http::post($end_point . "/place/addPlace", [
                'token' => $access_token,
                'name' => $name,
                'address' => $address,
            ]);

            if (!$response->successful()) {
                logger()->debug("create_hanet_place failed: ". $response->body());
            }

            $body = json_decode($response->body());

            if ($body->returnMessage != "SUCCESS") {
                logger()->debug("create_hanet_place failed: ". $response->body());
            }

            return $body->data;
        }
        catch (Exception $e) {
            logger()->error("create_hanet_place error: ". $e->getMessage());
            return null;
        }
    }

    private function updateHanetPlaceId($place, $hanet_place) {
        try {
            HanetPlace::where('id', $place['id'])
            ->update(['hanet_place_id' => $hanet_place->id]);
        }
        catch (Exception $e) {
            logger()->error("updateHanetPlaceId error: ". $e->getMessage());
        }
    }

    private function getHanetPlaceCamera($access_token, $hanet_place_id) {
        $end_point = config("hanet.partner_url");

        try {
            $response = Http::post($end_point . "/device/getListDeviceByPlace", [
                'token' => $access_token,
                'placeID' => $hanet_place_id,
            ]);

            if (!$response->successful()) {
                logger()->debug("get_hanet_camera_by_place failed: ". $response->body());
                return [];
            }

            $body = json_decode($response->body());

            if ($body->returnMessage != "SUCCESS") {
                logger()->debug("get_hanet_camera_by_place failed: ". $response->body());
                return [];
            }

            return $body->data;
        }
        catch (Exception $e) {
            logger()->error("create_hanet_place error: ". $e->getMessage());
            return [];
        }
    }

    private function deleteHanetPlace($access_token, $hanet_place_id) {
        $end_point = config("hanet.partner_url");

        try {
            $response = Http::post($end_point . "/place/removePlace", [
                'token' => $access_token,
                'placeID' => $hanet_place_id,
            ]);

            if (!$response->successful()) {
                logger()->debug("delete_hanet_place failed: ". $response->body());
                return;
            }

            $body = json_decode($response->body());

            if ($body->returnMessage != "SUCCESS") {
                logger()->debug("delete_hanet_place failed: ".$response->body());
                return;
            }

            logger()->debug('Delete HANET place ok');
        }
        catch (Exception $e) {
            logger()->error("delete_hanet_place error: ". $e->getMessage());
        }
    }
}
