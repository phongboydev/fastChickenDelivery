<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Jobs\HanetGetPlacesJob;
use App\Jobs\SyncHanetDevice;
use App\Jobs\SyncHanetPerson;
use App\Jobs\SyncHanetPlace;
use App\Models\ClientEmployee;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Models\HanetPlacePerson;
use App\Models\HanetSetting;
use App\Models\HanetLog;
use Auth;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Support\PeriodHelper;
use App\Support\Constant;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use App\Support\HanetHelper;
use App\Exceptions\CustomException;
use App\Models\ClientDepartmentHanet;
class HanetMutator
{

    use DispatchesJobs;

    /**
     * @throws \App\Exceptions\HumanErrorException
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function syncDevices(): string
    {
        $clientId = $this->checkAuthGetClientId();
        $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
        if (!$hanetSetting) {
            $hanetSetting->is_syncing_camera = true;
            $hanetSetting->save();
            throw new HumanErrorException(__("error.not_found", ['name' => __("config")]));
        }
        $this->dispatch(new SyncHanetDevice([
            "client_id" => $clientId,
        ]));
        return "ok";
    }

    /**
     * @throws \App\Exceptions\HumanErrorException
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function restartHanetLog($root, array $args): string
    {
        $id = isset($args['id']) ? $args['id'] : null;
        $date = isset($args['date']) ? $args['date'] : null;
        $clientId = isset($args['client_id']) ? $args['client_id'] : null;

        $query = HanetSetting::query();
        if ($clientId) {
            $query->whereHas('client', function ($subQuery) use ($clientId) {
                $subQuery->where('id', $clientId);
            });
        }
        $query
            ->with('client')
            ->chunk(100, function ($hanetSettings) use ($id, $date) {
                foreach ($hanetSettings as $hanetSetting) {
                    /** @var HanetSetting $hanetSetting */
                    if (!$hanetSetting->client) {
                        logger()->warning('HanetRecoverTimelogCommand: HanetSetting without Client found', [
                            'id' =>
                                $hanetSetting->id,
                        ]);
                        return;
                    }

                    // Adjust from to according to date begin mark
                    $workflowSetting = $hanetSetting->client->clientWorkflowSetting;
                    $dayBeginMark = $workflowSetting->getTimesheetDayBeginAttribute();
                    $dayBeginMarkCarbon = Carbon::parse($dayBeginMark, Constant::TIMESHEET_TIMEZONE);
                    $from = Carbon::parse($date, Constant::TIMESHEET_TIMEZONE)
                                  ->setHour($dayBeginMarkCarbon->hour)
                                  ->setMinute($dayBeginMarkCarbon->minute);
                    $to = $from->clone()->addDay();

                    // split $from and $to into 2 periods separated by 00:00
                    // Reason: Hanet API does not support querying across 00:00 (bug?)
                    $periods = [
                        [$from, $from->clone()->setTime(23, 59, 59)],
                    ];
                    if ($to->hour > 0 || $to->minute > 0) {
                        $periods[] = [$to->clone()->setTime(0, 0, 1), $to];
                    }

                    $places = [];
                    $endPoint = config('hanet.partner_url');
                    $accessToken = $hanetSetting->token;

                    $placesResponse = Http::post($endPoint.'/place/getPlaces', [
                        'token' => $accessToken,
                    ]);
                    
                    $placesResponseBody = $placesResponse->json();
                    $hanetLog = HanetLog::find($id);

                    $hanetLog->is_success = ( $placesResponseBody['returnMessage'] == "SUCCESS") ? true : false;
                    $hanetLog->response_data = $placesResponse;
                    $hanetLog->update();

                    if (isset($placesResponseBody['data']) && is_array($placesResponseBody['data'])) {
                        foreach ($placesResponseBody['data'] as $place) {
                            if (isset($place['id'])) {
                                $places[] = ['id' => $place['id']];
                            }
                        }
                    }
                    foreach ($places as $place) {
                        $checkInRecords = [];
                        foreach ($periods as $period) {
                            $this->fetchAll(
                                $checkInRecords,
                                $accessToken,
                                $place['id'],
                                $period[0],
                                $period[1]
                            );
                        }

                        foreach ($checkInRecords as $aliasId => $checkInRecord) {
                            $person = HanetPerson::query()->with("clientEmployee")
                                                ->where("client_id", $hanetSetting->client_id)
                                                ->where("alias_id", $aliasId)
                                                ->first();
                            if (!$person) {
                                continue;
                            }
                            $in = $checkInRecord["in"] ? Carbon::createFromTimestampMs($checkInRecord["in"],
                                Constant::TIMESHEET_TIMEZONE) : null;
                            $out = $checkInRecord["out"] ? Carbon::createFromTimestampMs($checkInRecord["out"],
                                Constant::TIMESHEET_TIMEZONE) : null;

                            if ($in) {
                                $person->clientEmployee->checkIn($date, PeriodHelper::getHourString($in),
                                    $date != $in->toDateString());
                            }
                            if ($out) {
                                $person->clientEmployee->checkOut($date, PeriodHelper::getHourString($out),
                                    $date != $out->toDateString());
                            }
                        }
                    }
                }
            });
        return "ok";
    }

    /**
     * @param  string  $accessToken
     * @param  string  $placeId
     * @param  \Illuminate\Support\Carbon  $from
     * @param  \Illuminate\Support\Carbon  $to
     */
    private function fetchAll(
        array &$checkInRecords,
        string $accessToken,
        string $placeId,
        Carbon $from,
        Carbon $to
    ): array {
        $page = 1;
        $endPoint = config('hanet.partner_url');
        // $checkInRecords = [];
        do {
            logger("= Page: ".$page);
            $response = Http::post($endPoint.'/person/getCheckinByPlaceIdInTimestamp', [
                'token' => $accessToken,
                'placeID' => $placeId,
                'from' => $from->getTimestampMs(),
                'to' => $to->getTimestampMs(),
                'page' => $page,
                'size' => 500,
            ]);

            if (!$response->successful()) {
                break;
            }

            $responseBody = $response->json();
            if (empty($responseBody['data'])) {
                logger("Empty data");
                break;
            }
            $logs = collect($responseBody['data'])->sortBy('checkinTime');

            // put
            foreach ($logs as $checkInData) {
                $checkInTimeHuman = Carbon::createFromTimestampMs($checkInData['checkinTime'],
                    Constant::TIMESHEET_TIMEZONE);
                logger('Data: AliasID='.$checkInData['aliasID'].',checkinTime='.$checkInTimeHuman.',deviceID='.$checkInData['deviceID']);
                if ($checkInData['aliasID']) {
                    $aliasId = $checkInData['aliasID'];
                    if (!isset($checkInRecords[$aliasId])) {
                        $checkInRecords[$aliasId] = [
                            'in' => '',
                            'out' => '',
                        ];
                    }

                    if (empty($checkInRecords[$aliasId]['in'])) {
                        $checkInRecords[$aliasId]['in'] = $checkInData['checkinTime'];
                    } else {
                        if($checkInData['checkinTime'] < $checkInRecords[$aliasId]['in']){
                            // set time checkout = time check in
                            if(empty($checkInRecords[$aliasId]["out"])){
                                $checkInRecords[$aliasId]["out"] = $checkInRecords[$aliasId]['in'];
                            }elseif($checkInRecords[$aliasId]["out"] < $checkInRecords[$aliasId]['in']){
                                $checkInRecords[$aliasId]["out"] = $checkInRecords[$aliasId]['in'];
                            }
                            // set time check in again
                            $checkInRecords[$aliasId]['in'] = $checkInData['checkinTime'];

                        } else {

                            if($checkInRecords[$aliasId]["out"]){
                                if($checkInRecords[$aliasId]["out"] < $checkInData['checkinTime']){
                                    $checkInRecords[$aliasId]["out"] = $checkInData['checkinTime'];
                                }
                            }else{
                                $checkInRecords[$aliasId]["out"] = $checkInData['checkinTime'];
                            }
                            
                        }
                    }
                }
            }

            if (count($responseBody['data']) < 500) {
                break;
            }
            $page++;
        } while (true);
        return $checkInRecords;
    }

    /**
     * @throws \App\Exceptions\HumanErrorException
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function getAccessToken($root, array $args): string
    {
        $clientId = $this->checkAuthGetClientId();
        $code = $args['code'];

        $end_point = config("hanet.oauth_url");

        $hanet_client_id = config("hanet.client_id");
        $hanet_client_secret = config("hanet.client_secret");

        try {
            $response = Http::post($end_point."/token", [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $hanet_client_id,
                'client_secret' => $hanet_client_secret,
            ]);

            if (!$response->successful()) {
                logger()->debug("get_hanet_access_token failed: ". $response->body());
            }

            $body = json_decode($response->body());

            $access_token = $body->access_token;
            $partner_token = $this->updateHanetPartnerToken($access_token, $clientId);

            $setting = [
                'id' => Str::uuid(),
                'client_id' => $clientId,
                'partner_token' => $partner_token,
                'token' => $access_token,
                'refresh_token' => $body->refresh_token,
                'expire' => $body->expire,
                'is_syncing_camera' => true,
                'expiration_date' => Carbon::now()->addDay(365)->format('Y-m-d H:m:i')
            ];

            HanetSetting::insert($setting);

            // đồng bộ một lần, sau khi vừa mới kết nối thành công
            $this->dispatch(new SyncHanetDevice([
                "client_id" => $clientId,
            ]));
            $this->dispatch(new HanetGetPlacesJob($clientId));

            return 'ok';
        } catch (Exception $e) {
            logger()->error("get_hanet_access_token error: ".$e->getMessage());
            throw new HumanErrorException(__("error.external"));
        }
    }

    private function updateHanetPartnerToken($access_token, $client_id) {
        try {
            $partner_token = hash('md5', $client_id);

            $partner_end_point = config("hanet.partner_url");

            $response = Http::post($partner_end_point."/partner/updateToken", [
                'access_token' => $access_token,
                'partner_token' => $partner_token,
            ]);

            if (!$response->successful()) {
                logger()->debug("update_hanet_partner_token failed: ".$response->body());
            }

            $body = json_decode($response->body());

            if ($body->returnMessage != 'SUCCESS') {
                $partner_token = "";
            }
        } catch (Exception $e) {
            logger()->error("update_hanet_partner_token failed: ".$response->body());
        }

        return $partner_token;
    }

    /**
     * @throws \App\Exceptions\HumanErrorException
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function syncPlaces(): string
    {
        $clientId = $this->checkAuthGetClientId();
        $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
        if (!$hanetSetting) {
            throw new HumanErrorException(__("error.not_found", ['name' => __("config")]));
        }
        $this->dispatch(new HanetGetPlacesJob($clientId));
        return "ok";
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function createHanetPlace($root, array $args): string
    {
        // check permission
        $clientId = $this->checkAuthGetClientId();

        try {
            $name = $args['name'];
            $address = $args['address'];
            $datetime = Carbon::now()->format('Y-m-d H:i:s');

            $place = [
                'id' => Str::uuid(),
                'client_id' => $clientId,
                'name' => $name,
                'address' => $address,
                'created_at' => $datetime,
                'updated_at' => $datetime,
            ];

            HanetPlace::insert($place);

            $this->dispatch(new SyncHanetPlace($place, 'create'));
            return 'ok';
        } catch (Exception $e) {
            logger()->error("createHanetPlace error: ".$e->getMessage());
            return 'fail';
        }
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     * @throws \Exception
     */
    public function updateHanetPlace($root, array $args)
    {
        $id = $args['id'];
        $input = $args['input'];

        // check permission
        $clientId = $this->checkAuthGetClientId();

        try {
            $place = HanetPlace::select('*')
                               ->where("client_id", $clientId)
                               ->where('id', $id)
                               ->first();

            if (is_null($place)) {
                return 'place_not_found';
            }

            $place->name = $input->name;
            $place->address = $input->address;

            $place->save();

            $this->dispatch(new SyncHanetPlace($place, 'update'));
        } catch (Exception $e) {
            logger()->error("updateHanetPlace error: ".$e->getMessage());
            return 'fail';
        }
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     * @throws \Exception
     */
    public function deleteHanetPlace($root, array $args)
    {
        $id = $args['id'];
        $clientId = $this->checkAuthGetClientId();

        try {
            $place = HanetPlace::select('*')
                               ->where("client_id", $clientId)
                               ->where('id', $id)
                               ->first();

            $hanet_place_id = $place->hanet_place_id;

            if (is_null($place)) {
                return 'place_not_found';
            }

            HanetPlace::destroy($id);
            $this->dispatch(new SyncHanetPlace(
                [
                    'client_id' => $clientId,
                    'hanet_place_id' => $hanet_place_id,
                ], 'delete'));
        } catch (Exception $e) {
            logger()->error("deleteHanetPlace error: ".$e->getMessage());
            return 'fail';
        }
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function syncHanetPerson($root, array $args)
    {
        $clientId = $this->checkAuthGetClientId();
        $args['client_id'] = $clientId;
        DB::transaction(function () use ($args, $clientId) {
            $ids = $args['id_list'];
            ClientEmployee::whereIn('id', $ids)->chunk(100, function ($clientEmployees) use ($clientId) {
                foreach ($clientEmployees as $clientEmployee) {
                    $hanetPerson = HanetPerson::where('client_id', $clientId)
                                              ->where('client_employee_id', $clientEmployee->id)
                                              ->first();

                    if (!$hanetPerson) {
                        $hanetPerson = HanetPerson::create([
                            'client_id' => $clientId,
                            'client_employee_id' => $clientEmployee->id,
                            'name' => $clientEmployee->full_name,
                            'title' => $clientEmployee->title,
                            'avatar' => '',
                            'alias_id' => $clientEmployee->code,
                            'sync_error' => '',
                        ]);
                    }
                    $hanetPerson->sync_error = 'In progress';
                    $hanetPerson->save();
                }
            });
            $this->dispatch(new SyncHanetPerson($args));
        });
        return 'ok';
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function removeHanetPlacePerson($root, array $args)
    {
        $clientId = $this->checkAuthGetClientId();
        try {
            HanetPlace::query()
                      ->where("client_id", $clientId)
                      ->where("hanet_place_id", $args['hanet_place_id'])
                      ->firstOrFail();
            $hanet_person_id = $args['hanet_person_id'];
            $hanet_place_id = $args['hanet_place_id'];

            $place_person = HanetPlacePerson::select('*')
                                            ->where('hanet_person_id', $hanet_person_id)
                                            ->where('hanet_place_id', $hanet_place_id)
                                            ->first();


            if (is_null($place_person)) {
                return 'place_not_found';
            }

            HanetPlacePerson::destroy($place_person->id);

            return 'ok';
        } catch (Exception $e) {
            logger()->error("removeHanetPlacePerson error: ".$e->getMessage());
            return 'fail';
        }
    }

    /**
     * @return mixed|string
     * @throws \Nuwave\Lighthouse\Exceptions\AuthenticationException
     */
    public function checkAuthGetClientId()
    {
        $user = Auth::user();
        if (!$user->hasAnyPermission(["manage-camera-checkin"])) {
            throw new AuthenticationException(__("error.permission"));
        }
        return $user->client_id;
    }

    /**
     * Create new person on Hanet
     */
    public function registerHanetPerson($root, array $args)
    {
        $clientId = $this->checkAuthGetClientId();
        if(!empty($clientId)) {
            $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
            $employee = ClientEmployee::where('id', $args['client_employee_id'])->first();

            // $place = HanetPlace::select('*')
            //                    ->where("client_id", $clientId)
            //                    ->where('hanet_place_id', $id)
            //                    ->first();
            $avatar = $employee->getAvatarHanetAttribute();
            // return true;
            if(!empty($hanetSetting->token) && !empty($employee->code) && !empty($avatar)) {     
                $data = [
                    'token' => $hanetSetting->token,
                    'name' => $employee->full_name,
                    'aliasID' => $employee->code,
                    'placeID' => $args['hanet_place_id'],
                    'url' => $avatar,
                    'title' => 'Mrs',
                    'type' => 0 
                ];

                $hanet = new HanetHelper();
                $response = $hanet->registerByUrl($data);
                $response = json_decode($response->getContents(), true);                
                if( !empty($response['data']) && !empty($response['data']['personID']) ) {
                    $dataPerson = $response['data'];
                    $hanetPerson = HanetPerson::updateOrCreate(
                    [
                        'client_id' => $clientId,                         
                        'client_employee_id' => $employee->id,
                        'person_id' => $dataPerson['personID']
                    ],
                    [
                        'alias_id' => $employee->code,
                        'title' => $employee->title,
                        'name' => $dataPerson['name'],
                        'avatar' => $dataPerson['file'],                        
                        'sync_error' => $response['returnMessage']
                    ]);

                    if(!empty($hanetPerson->id)) {
                        // map person hanet with hanet place
                        $hanetPlace = HanetPlace::query()->where('hanet_place_id', $args['hanet_place_id'])->where('client_id', $clientId)->first();
                        if(!empty($hanetPlace->id)) {
                            HanetPlacePerson::updateOrCreate(
                                ['hanet_person_id' => $hanetPerson->id,
                                'hanet_place_id' => $hanetPlace->id
                                ]
                            );
                            return $hanetPerson;
                        }
                    }
                    
                } else {
                    throw new CustomException(
                        'Create person hanet not success.',
                        'HumanErrorException'
                    );
                }
                
            }else { 
                if(empty($hanetSetting->token)) {
                    throw new CustomException(
                        'Token hanet not available.',
                        'HumanErrorException'
                    );
                }
                if(empty($employee->code)) {
                    throw new CustomException(
                        'Employee not available.',
                        'HumanErrorException'
                    );
                }
                if(empty($avatar)) {
                    throw new CustomException(
                        'Avatar not available.',
                        'HumanErrorException'
                    );
                }
                
            }
            
        } else {
            throw new CustomException(
                'Client not available.',
                'AuthorizedException'
            );
        }
        
    }

    /**
     * Create department on hanet
     */

     public function createHanetDepartment($root, array $args) {

        $clientId = $this->checkAuthGetClientId();
        if(!empty($clientId)) {
            $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
            if( !empty($args['hanet_place_id']) && !empty($args['name']) && !empty($args['department_id']) ) 
            {
                $data = [
                    'token' => $hanetSetting->token,
                    'placeID' => $args['hanet_place_id'],
                    'name' => $args['name'],
                ];
                $hanet = new HanetHelper();
                $response = $hanet->departmentCreate($data);
                $response = json_decode($response->getContents(), true);
                // $response = json_decode('{"returnCode":1,"returnMessage":"Success","data":{"createdAt":"1690104870188","desc":"","enable":0,"id":7461,"name":"Test IT 1","numEmployee":"0","placeId":"6783","status":0,"updatedAt":"1690104870188"}}', true);    
                if(!empty($response) && $response['returnMessage'] == 'Success' && !empty($response['data'])) 
                {
                    if(!empty($response['data']['id']))
                    {
                        $ClientDepartmentHanet = ClientDepartmentHanet::updateOrCreate([
                            'hanet_department_id' => $response['data']['id'],
                            'client_department_id' => $args['department_id'],
                            'hanet_place_id' => $args['hanet_place_id'],
                            'name' => $args['name'],
                            'desc' => $args['desc']
                        ]);
                        return $ClientDepartmentHanet;
                    }
                    else
                    {
                        throw new CustomException(
                            'Create department hanet not successfully!',
                            'HumanErrorException'
                        );
                    }
                } 
                
            } else {

                if(empty($args['hanet_place_id'])) {
                    throw new CustomException(
                        'Place hanet not available.',
                        'HumanErrorException'
                    );
                }

                if(empty($args['name'])) {
                    throw new CustomException(
                        'Name not available.',
                        'HumanErrorException'
                    );
                }

                if(empty($args['department_id'])) {
                    throw new CustomException(
                        'Department not available.',
                        'HumanErrorException'
                    );
                }
                
            }
        }        

     }

     /**
      * update department on Hanet
      */
     public function updateHanetDepartment($root, array $args) {
        $clientId = $this->checkAuthGetClientId();
        if(!empty($clientId)) {
            $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
            if( !empty($args['hanet_place_id']) && !empty($args['name']) && !empty($args['department_id']) ) {
                $data = [
                    'token' => $hanetSetting->token,
                    'placeID' => $args['hanet_place_id'],
                    'name' => $args['name'],
                    'id' => $args['hanet_department_id']
                ];
                $hanet = new HanetHelper();
                $response = $hanet->departmentUpdate($data);
                $response = json_decode($response->getContents(), true);
                if(!empty($response) && $response['returnMessage'] == 'Success') {

                    $ClientDepartmentHanet = ClientDepartmentHanet::updateOrCreate([
                        'hanet_department_id' => $args['hanet_department_id'],
                        'client_department_id' => $args['department_id'],
                        'hanet_place_id' => $args['hanet_place_id'],
                        'name' => $args['name'],
                        'desc' => $args['desc']
                    ]);
                    return $ClientDepartmentHanet;

                }else
                {
                    throw new CustomException(
                        'Update department hanet not successfully!',
                        'HumanErrorException'
                    );
                }
                
            }
        }

     }

     public function refreshToken($root, array $args) {
        $clientId = $this->checkAuthGetClientId();
        $hanetSetting = HanetSetting::query()->where("client_id", $clientId)->first();
        if ($hanetSetting) { 
            $hanet = new HanetHelper();
            $data = [
                'grant_type' => 'refresh_token',
                'client_id' => config('hanet.client_id'),
                'client_secret' => config('hanet.client_secret'),
                'refresh_token' => $hanetSetting->refresh_token
            ];
            $hanet = $hanet->refreshToken($data);
            $response = json_decode($hanet->getContents(), true);
            if(!empty($response['refresh_token']) && !empty($response['access_token'] && !empty($response['expire']))) {
                $hanetSetting->token = $response['access_token'];
                $hanetSetting->expiration_date = Carbon::createFromTimestamp($response['expire'])->format('Y-m-d H:i:s');
                $hanetSetting->refresh_token = $response['refresh_token'];
                $hanetSetting->expire = $response['expire'];
                $hanetSetting->status = 'Success';
                $hanetSetting->save();
            } else {
                $hanetSetting->status = empty($response->returnMessage)  ? 'Fail' : $response->returnMessage;
                $hanetSetting->save();
                throw new HumanErrorException(__("ERR0001.internal.error"));
            }   
        }
        return true;
        
     }
}
