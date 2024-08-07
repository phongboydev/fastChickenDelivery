<?php

namespace App\Http\Controllers;

use App\DTO\HanetCheckinEvent;
use App\DTO\HanetDeviceEvent;
use App\DTO\HanetPlaceEvent;
use App\Jobs\HanetCheckinEventHandler;
use App\Jobs\HanetDeviceEventHandler;
use App\Jobs\HanetPlaceEventHandler;
use Illuminate\Http\Request;
use App\Models\ClientLogDebug;
use App\Models\HanetSetting;
use App\Models\TimesheetHanetTmp;
use App\Models\Timesheet;
use App\Models\HanetPerson;
use Illuminate\Support\Carbon;

class HanetController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query("secret") != config("hanet.client_secret")) {
            return "no authorization";
        }
               

        logger('HanetController@index: HANET webhook called.', ['request' => $request->toArray()]);
        $keycode = $request->input("keycode");
        $aliasID = $request->input("aliasID"); 
        $place_id = $request->input("placeID");
        
        if(empty($aliasID)){
            return "Alias id is empty!";
        }

        $client_id = '';
        // Get client id
        if($keycode){
            $setting = HanetSetting::select('client_id')
            ->where('partner_token', $keycode)
            ->first();
            if(!empty($setting->client_id)) {
                $client_id = $setting->client_id;
            }
            
        }

        $data_type = $request->input("data_type");

        if ('log' == $data_type) {
            logger("HanetController@index: HANET check in");
            // $this->checkin($request);
            $this->storeHanetTemp($request, $client_id);
        } elseif ('device' == $data_type) {
            logger("HanetController@index: HANET device update");
            $this->device($request);
        } elseif ('place' == $data_type) {
            logger("HanetController@index: HANET place update");
            $this->place($request);
        }
        return "ok";
    }

    private function device(Request $request)
    {
        $device_event = new HanetDeviceEvent($request);
        $this->dispatch(new HanetDeviceEventHandler($device_event));
    }

    private function place(Request $request)
    {
        $place_event = new HanetPlaceEvent($request);
        $this->dispatch(new HanetPlaceEventHandler($place_event));
    }

    private function storeHanetTemp(Request $request, $client_id) {

        $clientEmployee = $this->getClientEmployee($client_id, $request->input("aliasID"));
        TimesheetHanetTmp::updateOrCreate( 
            [
                'client_employee_id' => (!empty($clientEmployee)) ? $clientEmployee->id : null,
                'alias_id'  =>  $request->input("aliasID"),
                'date_time' => $request->input("date"),
                'person_id' => $request->input("personID")
            ],
            [                
                'client_id' =>  $client_id,
                'data_hanet' => json_encode($request->input()),
                'status' => 0,
            ]);
    }

    private function getClientEmployee($clientId, $alias_id)
    {
        $person = HanetPerson::select('client_employee_id')
            ->where('client_id', $clientId)
            ->where('alias_id', $alias_id)
            ->first();
        if ($person == null) {
            return null;
        }
        return $person->clientEmployee;
    }

    private function checkin(Request $request)
    {
        $checkin_event = new HanetCheckinEvent($request);
        $this->dispatch(new HanetCheckinEventHandler($checkin_event));
    }
}
