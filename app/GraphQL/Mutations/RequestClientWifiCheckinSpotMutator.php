<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientWifiCheckinSpot;

class RequestClientWifiCheckinSpotMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // $user = auth()->user();
        // $clientId = $user->client_id;

        $ssid = $args['ssid'];
        $mac = $args['mac'];
        $lat = $args['lat'];
        $lng = $args['lng'];

        // find wifi by ssid
        $wifi = ClientWifiCheckinSpot::authUserAccessible()
                ->where('spot_mac', $mac)
                // ->where('client_id', $clientId)
                ->where('spot_ssid', $ssid)
                ->first();

        if ($wifi) {
            // existed wifi
            return $wifi;
        }

        // try find by ssid
        $wifi = ClientWifiCheckinSpot::authUserAccessible()
            ->where('spot_ssid', $ssid)
            // ->where('client_id', $clientId)
            ->first();

        if (!$wifi) {
            return null;
        }

        // check if found wifi name and location is within the radius
        $wifiLat = $wifi->latitude;
        $wifiLng = $wifi->longitude;
        $wifiRadius = $wifi->radius;

        $distance = $this->distance($lat, $lng, $wifiLat, $wifiLng);
        if ($distance > $wifiRadius) {
            return null;
        }

        // if within create new checkin spot
        $newWifi = ClientWifiCheckinSpot::create([
            'client_id' => $wifi->client_id,
            'spot_name' => $wifi->spot_name,
            'spot_ssid' => $wifi->spot_ssid,
            'spot_mac' => $mac,
            'memo' => $wifi->memo,
            'longitude' => $wifi->longitude,
            'latitude' => $wifi->latitude,
            'radius' => $wifi->radius,
        ]);

        return $newWifi;
    }

    private function distance($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371; // Radius of the earth in km
        $dLat = deg2rad($lat2 - $lat1);  // deg2rad below
        $dLon = deg2rad($lon2 - $lon1);
        $a =
            sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2)
        ;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $d = $R * $c; // Distance in km
        return $d * 1000; // convert to meters
    }
}
