<?php

namespace App\Jobs;

use App\Models\HanetDevice;
use App\Models\HanetSetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncHanetDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $args;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($args)
    {
        $this->args = $args;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $args = $this->args;
        $client_id = $args['client_id'];

        $devices = $this->getDevices($client_id);

        $this->insertDevices($client_id, $devices);
    }

    private function getDevices($client_id) {

        $setting = HanetSetting::select('token')
        ->where('client_id', $client_id)
        ->first();

        if (is_null($setting) || is_null($setting->token)) {
            logger()->error("create_hanet_place error: access_token is empty");
            return null;
        }

        $access_token = $setting->token;

        $end_point = config("hanet.partner_url");

        // Fake request
        // Http::fake([
        //     '*' => Http::response("{\"returnCode\":1,\"returnMessage\":\"SUCCESS\",\"data\":[{\"address\":\"\",\"deviceID\":\"C20371B106\",\"deviceName\":\"Văn Phòng Vip\",\"placeName\":\"Nhà\"}]}", 200),
        // ]);

        try {
            $response = Http::post($end_point . "/device/getListDevice", [
                'token' => $access_token,
            ]);

            if (!$response->successful()) {
                logger()->debug("sync_hanet_devices failed: ". $response->body());
                return [];
            }

            $body = json_decode($response->body());

            if ($body->returnCode != 1) {
                logger()->debug("sync_hanet_devices failed: ".$response->body());
                return [];
            }

            return $body->data;
        } catch (Exception $e) {
            logger()->error("sync_hanet_devices error: ".$e->getMessage());
            return [];
        } finally {
            $setting->is_syncing_camera = false;
            $setting->save();
        }
    }

    private function insertDevices($client_id, $devices) {
        $datetime = Carbon::now()->format('Y-m-d H:i:s');

        HanetDevice::query()
                   ->where("client_id", $client_id)
                   ->delete();

        $new_devices = array_map(function ($item) use ($client_id, $datetime) {
            $new_device = [
                'id' => Str::uuid(),
                'client_id' => $client_id,
                'name' => $item->deviceName,
                'device_id' => $item->deviceID,
                'place_id' => '',
                'address' => $item->address,
                'place_name' => $item->placeName,
                'created_at' => $datetime,
                'updated_at' => $datetime,
            ];

            return $new_device;
        }, $devices);

        $updatedIds = [];
        foreach ($new_devices as $device) {
            $hanetDevice = HanetDevice::query()->where("client_id", $client_id)
                                      ->where('device_id', $device["device_id"])
                                      ->first();

            if (!$hanetDevice) {
                $hanetDevice = new HanetDevice($device);
            }
            // update old hanetDevice
            $hanetDevice->fill($device);
            $hanetDevice->synced = true;
            $hanetDevice->save();
            $updatedIds[] = $hanetDevice->id;
        }
        HanetDevice::query()->where("client_id", $client_id)
                   ->whereNotIn("id", $updatedIds)
                   ->update([
                       "synced" => 0,
                   ]);
        // done
    }
}
