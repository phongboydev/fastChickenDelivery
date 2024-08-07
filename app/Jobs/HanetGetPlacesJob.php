<?php

namespace App\Jobs;

use App\Models\HanetPlace;
use App\Models\HanetSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class HanetGetPlacesJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientId;

    public function __construct(string $clientId)
    {
        $this->clientId = $clientId;
    }

    public function handle()
    {
        $hanetSettings = HanetSetting::query()->where("client_id", $this->clientId)->first();
        // {
        //   "returnCode": 1,
        //   "returnMessage": "SUCCESS",
        //   "data": [
        //     {
        //       "address": "",
        //       "name": "Đoàn TNCS Quảng Ninh",
        //       "id": 128,
        //       "userID": 1495348707219832800
        //     },
        //     {
        //         "address": "",
        //       "name": "Test 5",
        //       "id": 326,
        //       "userID": 1697808719422374000
        //     },
        //     {
        //         "address": "",
        //       "name": "Thư Viện Tỉnh Quảng Ninh",
        //       "id": 466,
        //       "userID": 1697808719422374000
        //     },
        //     {
        //         "address": "125/61 D1 Phường 25 Quận Bình Thạnh",
        //       "name": "Team dev",
        //       "id": 108,
        //       "userID": 1736877063931822000
        //     },
        //     {
        //         "address": "",
        //       "name": "Sở KHCN Quảng Ninh",
        //       "id": 115,
        //       "userID": 1495348707219832800
        //     },
        //     {
        //         "address": "",
        //       "name": "Camera HCM",
        //       "id": 142,
        //       "userID": 1737430314640892000
        //     },
        //     {
        //         "address": "",
        //       "name": "Hanet Hạ Long Test",
        //       "id": 143,
        //       "userID": 1697808719422374000
        //     },
        //     {
        //         "address": "",
        //       "name": "devitop",
        //       "id": 292,
        //       "userID": 1749807728949739500
        //     },
        //     {
        //         "address": "",
        //       "name": "Welcome",
        //       "id": 293,
        //       "userID": 1749834454148857900
        //     },
        //     {
        //         "address": "",
        //       "name": "Welcome",
        //       "id": 297,
        //       "userID": 1749984263653884000
        //     },
        //     {
        //       "address": "",
        //       "name": "test",
        //       "id": 401,
        //       "userID": 1495348707219832800
        //     }
        //   ]
        // }
        if ($hanetSettings) {
            $accessToken = $hanetSettings->token;
            $places = $this->getPlaces($accessToken);
            foreach ($places as $place) {
                HanetPlace::where("client_id", $this->clientId)
                          ->where("hanet_place_id", $place->id)
                          ->firstOrCreate([
                              "client_id" => $this->clientId,
                              "address" => $place->address,
                              "name" => $place->name,
                              "hanet_place_id" => $place->id,
                          ]);
            }
        }
    }

    private function getPlaces($accessToken)
    {
        $endPoint = config("hanet.partner_url");
        try {
            $response = Http::post($endPoint."/place/getPlaces", [
                'token' => $accessToken,
            ]);

            if (!$response->successful()) {
                logger()->debug("sync_hanet_places failed: ".$response->body());
                return [];
            }

            $body = json_decode($response->body());

            if ($body->returnCode != 1) {
                logger()->debug("sync_hanet_places failed: ".$response->body());
                return [];
            }

            return $body->data;
        } catch (Exception $e) {
            logger()->error("sync_hanet_places error: ".$e->getMessage());
            return [];
        }
    }
}
