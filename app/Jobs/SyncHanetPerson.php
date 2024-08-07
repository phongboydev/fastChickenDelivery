<?php

namespace App\Jobs;

use App\Models\ClientEmployee;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Models\HanetPlacePerson;
use App\Models\HanetSetting;
use App\Support\Constant;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Support\HanetHelper;

class SyncHanetPerson implements ShouldQueue
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
        $id_list = $args['id_list'];

        try {

            $places = HanetPlace::where('client_id', $client_id)
                ->get()
                ->toArray();

            if (empty($places)) {
                logger()->debug('Stop sync hanet person. Place is empty');

                return;
            }


            $this->sync($client_id, $id_list, $places);
        } catch (Exception $e) {
            logger()->error($e->getMessage());
        }
    }

    /**
     * @param $client_id
     * @param $id_list list of ClientEmployee's id
     * @param $places
     *
     * @return void|null
     */
    private function sync($client_id, $id_list, $places)
    {
        try {
            $setting = HanetSetting::select('token')
                                   ->where('client_id', $client_id)
                                   ->first();

            if (is_null($setting) || is_null($setting->token)) {
                logger()->error("pushPersons error: access_token is empty");
                return null;
            }

            $access_token = $setting->token;

            foreach($id_list as $client_emp_id) {
                foreach($places as $place) {
                    $this->pushPerson($access_token, $client_id, array($client_emp_id), $place['id'], $place['hanet_place_id']);
                }
            }
        } catch(Exception $e) {
            logger()->error('sync hanet person ' . $e->getMessage());
        }
    }

    private function getPersons($client_id, $hanet_place_id) {

        $setting = HanetSetting::select('token')
        ->where('client_id', $client_id)
        ->first();

        if (is_null($setting) || is_null($setting->token)) {
            logger()->error("get_hanet_persons error: access_token is empty");
            return null;
        }

        $access_token = $setting->token;

        $end_point = config("hanet.partner_url");

        // Fake request
        // Http::fake([
        //     '*' => Http::response("{\"returnCode\":1,\"returnMessage\":\"SUCCESS\",\"data\":[{\"aliasID\":\"DM_010\",\"name\":\"Diều\",\"avatar\":\"https://static.hanet.ai/face/employee/401/7c7814fb-a06a-45fd-abd5-bb4e1c0a8b1d.jpg\",\"title\":\"\"},{\"aliasID\":\"DM_005\",\"name\":\"Hòa\",\"avatar\":\"https://static.hanet.ai/face/employee/401/cede69e6-574a-466c-a59d-491537917064.jpg\",\"title\":\"DM_012\"},{\"aliasID\":\"DM_016\",\"name\":\"Như\",\"avatar\":\"https://static.hanet.ai/face/employee/401/4de4cb6c-b2d9-41ae-a6c7-cf09b049a2d9.jpg\",\"title\":\"\"},{\"aliasID\":\"DM_002\",\"name\":\"Phí\",\"avatar\":\"https://static.hanet.ai/face/employee/401/1051b7f0-cb07-41ea-acf7-5e97af879402.jpg\",\"title\":\"\"}]}", 200),
        // ]);

        try {
            $response = Http::post($end_point . "/person/getListByPlace", [
                'token' => $access_token,
                'placeID' => $hanet_place_id,
                'type' => 0
            ]);

            if (!$response->successful()) {
                logger()->debug("get_hanet_persons failed: ". $response->body());
                return [];
            }

            $body = json_decode($response->body());

            if ($body->returnMessage != 'SUCCESS') {
                logger()->debug("get_hanet_persons failed: ". $response->body());
                return [];
            }

            return $body->data;
        }
        catch (Exception $e) {
            logger()->error("sync_hanet_devices error: ". $e->getMessage());
            return [];
        }
    }

    private function pushPerson($access_token, $client_id, $client_emp_id, $place_id, $hanet_place_id) {
        $clientEmployee = ClientEmployee::where('id', $client_emp_id)->first();

        if (!$clientEmployee) {
            logger()->debug('SyncHanetPerson sync not existed ClientEmployee');
            return;
        }
        
        if($clientEmployee && !empty($clientEmployee->hanetPerson)) {
            if($clientEmployee->hanetPerson) {
                $hanetPlacePerson = HanetPlacePerson::join('hanet_places', 'hanet_place_persons.hanet_place_id', '=', 'hanet_places.id')
                ->where('hanet_places.hanet_place_id', '=', $hanet_place_id) 
                ->where('hanet_place_persons.hanet_person_id', '=', $clientEmployee->hanetPerson->id) 
                ->first();
                $hanet = new HanetHelper();
                // check update info employee
                if(!empty($hanetPlacePerson) && !empty($hanetPlacePerson->person_id)) {
                    return $this->updatePersonAndAvatar($hanet, $hanetPlacePerson, $clientEmployee, $hanet_place_id, $access_token);

                } else {                    

                    return $this->processPersonWithoutPlacePerson($hanet, $clientEmployee, $place_id, $hanet_place_id, $access_token);
                }
            }
        }
        return true;

        

        /*
        $dataRequestPerson = [
            'token' => $access_token
        ];

        if($clientEmployee->hanetPerson->person_id) {
            $dataRequestPersonInfo = $dataRequestPerson;
            $dataRequestPersonInfo['personID'] =  $clientEmployee->hanetPerson->person_id;
            $dataPerson = [
                'name' => $clientEmployee->full_name,
                'title' => $clientEmployee->title,
                'aliasID' => $clientEmployee->code
            ];
            $dataRequestPersonInfo['placeID'] = $hanet_place_id;
            $dataRequestPersonInfo['updates'] = json_encode($dataPerson);
            //update info person by person id
            $hanet = new HanetHelper();
            $response = $hanet->personUpdate($dataRequestPersonInfo);
            $responseBopdy = json_decode($response->getContents(), true);
            if( !empty($responseBopdy['returnCode']) && $responseBopdy['returnCode'] == 1 &&  $responseBopdy['returnMessage'] == 'Success') {
                $curr_person = HanetPerson::where('person_id', $clientEmployee->hanetPerson->person_id)->first();
                if( $curr_person ) {
                    $curr_person->name = $clientEmployee->full_name;
                    $curr_person->title = $clientEmployee->title;
                    $curr_person->alias_id = $clientEmployee->code;
                    $curr_person->save();
                }
            } else {
                return false;
            }

            //Update update By FaceUr By PersonID
            $dataRequestFaceUrl = $dataRequestPerson;
            $dataRequestFaceUrl['personID'] =  $clientEmployee->hanetPerson->person_id;
            $dataRequestFaceUrl['placeID'] = $hanet_place_id;
            
            $avatar = $clientEmployee->getAvatarHanetAttribute();
            if ($avatar == Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT) {
                $avatar = null;
                return "Avatar not set";
            }
            
            $dataRequestFaceUrl['url'] = $avatar;
            $response = $hanet->updateByFaceUrlByPersonID($dataRequestFaceUrl);
            $responseBopdy = json_decode($response->getContents(), true);
            if( !empty($responseBopdy['returnCode']) &&  $responseBopdy['returnCode'] == 1 && !empty($responseBopdy['data']) && $responseBopdy['data']['path']) {
                
                $curr_person->avatar = $responseBopdy['data']['path'];
                $curr_person->save();

            } else {
                return false;
            }

        } else {
            // create new person or update person not person id
            $syncResult = $this->pushPersonToHanet($access_token, $clientEmployee, $hanet_place_id);

            $datetime = Carbon::now()->format('Y-m-d H:i:s');
            $msgResult = (!empty($syncResult['returnMessage'])) ? $syncResult['returnMessage'] : '';
            logger("Error message ".$msgResult);

            $new_person = [
                'id' => Str::uuid(),
                'client_id' => $client_id,
                'client_employee_id' => $clientEmployee->id,
                'name' => $clientEmployee->full_name,
                'title' => $clientEmployee->title,
                'avatar' => (!empty($syncResult['data']['file'])) ? $syncResult['data']['file']: null,
                'alias_id' => $clientEmployee->code,
                'person_id' => (!empty($syncResult['data']['personID'])) ? $syncResult['data']['personID']: null,
                'created_at' => $datetime,
                'updated_at' => $datetime,
                'sync_error' => $msgResult,
            ];

            $place_person = array(
                'id' => Str::uuid(),
                'hanet_person_id' => $new_person['id'],
                'hanet_place_id' => $place_id,
                'created_at' => $datetime,
                'updated_at' => $datetime
            );

            $curr_person = HanetPerson::where('client_id', $new_person['client_id'])
                ->where('client_employee_id', $new_person['client_employee_id'])
                ->first();
            

            if (is_null($curr_person)) {
                HanetPerson::insert(array($new_person));
                HanetPlacePerson::insert(array($place_person));
            } else {
                // Get personid and avatar
                if($curr_person && empty($curr_person->person_id)) {                
                    $persionid = null;
                    $avatar = null;
                    if ( !empty($syncResult['data']['file']) && !empty($syncResult['data']['personID'])) {
                        $persionid = $syncResult['data']['personID'];
                        $avatar = $syncResult['data']['file'];
                    } else {
                        // call hanet get info employee
                        $hanet = new HanetHelper();                        
                        $dataRequestPerson['aliasID'] = $clientEmployee->code;

                        $response = $hanet->getUserInfoByAliasID($dataRequestPerson);
                        $response = json_decode($response->getContents(), true);
                        if($response['returnMessage'] == 'Success' && !empty($response['data']) && !empty($response['data']['0']['personID'])){
                            $persionid = $response['data']['0']['personID'];;
                            $avatar = $response['data']['0']['avatar'];                                    
                        }
                    }
                    
                    if(is_null($curr_person)) {
                        $new_person['person_id'] = $persionid;
                        $new_person['avatar'] = $avatar;
                    } else {
                        $curr_person->person_id = $persionid;
                        $curr_person->avatar = $avatar;
                    }            
                }

                $curr_person->sync_error = $msgResult;
                $curr_person->save();
                $relation_place_person = HanetPlacePerson::where('hanet_person_id', $curr_person['id'])
                                                        ->where('hanet_place_id', $place_id)
                                                        ->first();

                if (is_null($relation_place_person)) {
                    HanetPlacePerson::insert([
                        [
                            'id' => Str::uuid(),
                            'hanet_person_id' => $curr_person['id'],
                            'hanet_place_id' => $place_id,
                            'created_at' => $datetime,
                            'updated_at' => $datetime,
                        ],
                    ]);
                }
            }
        }*/
        
    }

    /**
     * Update avatar and person info
     */
    private function updatePersonAndAvatar($hanet, $hanetPlacePerson, $clientEmployee, $hanet_place_id, $access_token) {
        $person_id = $hanetPlacePerson->person_id;
        $dataRequestPerson = [
            'token' => $access_token
        ];
        $dataRequestPersonInfo = $dataRequestPerson;
        $dataRequestPersonInfo['personID'] =  $person_id;
        $dataPerson = [
            'name' => $clientEmployee->full_name,
            'title' => $clientEmployee->title,
            'aliasID' => $clientEmployee->code
        ];
        $dataRequestPersonInfo['placeID'] = $hanet_place_id;
        $dataRequestPersonInfo['updates'] = json_encode($dataPerson);
        //update info person by person id

        $response = $hanet->personUpdate($dataRequestPersonInfo);
        $responseBopdy = json_decode($response->getContents(), true);

        if( !empty($responseBopdy['returnCode']) && $responseBopdy['returnCode'] == 1 &&  $responseBopdy['returnMessage'] == 'Success') {
            $curr_person = HanetPerson::where('client_employee_id', $clientEmployee->id)->first();
            if( $curr_person ) {
                $curr_person->name = $clientEmployee->full_name;
                $curr_person->title = $clientEmployee->title;
                $curr_person->alias_id = $clientEmployee->code;
                $curr_person->sync_error = $responseBopdy['returnMessage'];
                $curr_person->save();
            }
        } else {
            return false;
        }

        //Update update By FaceUr By PersonID
        $dataRequestFaceUrl = $dataRequestPerson;
        $dataRequestFaceUrl['personID'] =  $person_id;
        $dataRequestFaceUrl['placeID'] = $hanet_place_id;
        
        $avatar = $clientEmployee->getAvatarHanetAttribute();
        if ($avatar == Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT) {
            $avatar = null;
            return "Avatar not set";
        }
        
        $dataRequestFaceUrl['url'] = $avatar;
        $response = $hanet->updateByFaceUrlByPersonID($dataRequestFaceUrl);
        $responseBopdy = json_decode($response->getContents(), true);

        if( !empty($responseBopdy['returnCode']) &&  $responseBopdy['returnCode'] == 1 && !empty($responseBopdy['data']) && $responseBopdy['data']['path']) {
            
            $curr_person->avatar = $responseBopdy['data']['path'];
            $curr_person->save();

        } else {
            return false;
        }
    }

    /**
     * process person without place
     */
    private function processPersonWithoutPlacePerson($hanet, $clientEmployee, $place_id, $hanet_place_id, $access_token) {
        $data = [
            'token' => $access_token,
            'aliasID' => $clientEmployee->code,
            'placeIDs' => $hanet_place_id,
        ];

        $response = $hanet->getListByAliasIDAllPlace($data);
        $responseBopdy = json_decode($response->getContents(), true);
        $dataPersonHanet = [];
        if(!empty($responseBopdy['data'])) {
            foreach($responseBopdy['data'] as $person) {
                if($person['placeID'] == $hanet_place_id) {
                    $dataPersonHanet = $person;
                    break;
                }
            }
        }

        if(!empty($dataPersonHanet) && !empty($dataPersonHanet['personID'] && $dataPersonHanet)) {

            $curr_person = HanetPerson::where('client_id', $clientEmployee->client_id)
                ->where('client_employee_id', $clientEmployee->id)
                ->first();
            // add person id to place
            $relation_place_person =$this->processPlacePerson($curr_person['id'], $place_id, $dataPersonHanet['personID']);
            
            $avatar = $clientEmployee->getAvatarHanetAttribute();

            if ($avatar == Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT) {
                $avatar = null;
                return "Avatar not set";
            }
            $dataRequestFaceUrl = [];
            $dataRequestFaceUrl['token '] = $access_token;
            $dataRequestFaceUrl['personID'] =  $relation_place_person->person_id;
            $dataRequestFaceUrl['placeID'] = $place_id;
            $dataRequestFaceUrl['url'] = $avatar;

            $response = $hanet->updateByFaceUrlByPersonID($dataRequestFaceUrl);
            $responseBopdy = json_decode($response->getContents(), true);
        
            if( !empty($responseBopdy['returnCode']) &&  $responseBopdy['returnCode'] == 1 && !empty($responseBopdy['data']) && $responseBopdy['data']['path']) {
                
                $curr_person->avatar = $responseBopdy['data']['path'];
                $curr_person->save();

            } else {
                return false;
            }


        } else {
            // create new person hanet
            $syncResult = $this->pushPersonToHanet($access_token, $clientEmployee, $hanet_place_id);

            $datetime = Carbon::now()->format('Y-m-d H:i:s');

            $msgResult = (!empty($syncResult['returnMessage'])) ? $syncResult['returnMessage'] : '';

            $new_person = [
                'id' => Str::uuid(),
                'client_id' => $clientEmployee->client_id,
                'client_employee_id' => $clientEmployee->id,
                'name' => $clientEmployee->full_name,
                'title' => $clientEmployee->title,
                'avatar' => '',
                'alias_id' => $clientEmployee->code,
                'created_at' => $datetime,
                'updated_at' => $datetime,
                'sync_error' => $syncResult ?? "",
            ];

            $place_person = array(
                'id' => Str::uuid(),
                'hanet_person_id' => $new_person['id'],
                'hanet_place_id' => $place_id,
                'person_id'=>(!empty($syncResult['data']['personID'])) ? $syncResult['data']['personID']: null,
                'created_at' => $datetime,
                'updated_at' => $datetime
            );

            $curr_person = HanetPerson::where('client_id', $new_person['client_id'])
                ->where('client_employee_id', $new_person['client_employee_id'])
                ->first();

            if (is_null($curr_person)) {
                HanetPerson::insert(array($new_person));
                HanetPlacePerson::insert(array($place_person));
            } else {
                $curr_person->sync_error = $msgResult;
                $curr_person->save();
                $relation_place_person = HanetPlacePerson::where('hanet_person_id', $curr_person['id'])
                                                        ->where('hanet_place_id', $place_id)
                                                        ->first();

                if (empty($relation_place_person->id)) {
                    HanetPlacePerson::insert([
                        [
                            'id' => Str::uuid(),
                            'hanet_person_id' => $curr_person['id'],
                            'hanet_place_id' => $place_id,
                            'person_id'=>(!empty($syncResult['data']['personID'])) ? $syncResult['data']['personID']: null,
                            'created_at' => $datetime,
                            'updated_at' => $datetime,
                        ],
                    ]);
                }
            }
        }
    }
    /**
     * Add persion id to place
     * 
     */
    private function processPlacePerson($hanet_person_id, $place_id, $personID = null) {
        $relation_place_person = HanetPlacePerson::where('hanet_person_id', $hanet_person_id)
        ->where('hanet_place_id', $place_id)
        ->first();
        if (empty($relation_place_person->id)) {
            HanetPlacePerson::insert([
                [
                    'id' => Str::uuid(),
                    'hanet_person_id' => $hanet_person_id,
                    'hanet_place_id' => $place_id,
                    'person_id'=>(!empty($personID)) ? $personID: null
                ],
            ]);
        } elseif(is_null($relation_place_person->person_id)) {            
            $relation_place_person->person_id = $personID;
            $relation_place_person->save();
        }
        return $relation_place_person;
    }
    /**
     * @param $access_token
     * @param $client_employee
     * @param $hanet_place_id
     *
     * @return string|null error message
     */
    private function pushPersonToHanet($access_token, $client_employee, $hanet_place_id)
    {
        $end_point = config("hanet.partner_url");

        // Fake api
        // Http::fake([
        //     '*' => Http::response("{\"returnCode\":1,\"returnMessage\":\"SUCCESS\",\"data\":{\"aliasID\":\"DM_010\",\"name\":\"Nguyễn ngọc Mai\",\"title\":\"Dev\"}}", 200),
        // ]);

        $avatar = $client_employee->getAvatarHanetAttribute();
        if ($avatar == Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT) {
            $avatar = null;
            return "Avatar not set";
        }

        for ($i = 0; $i < 2; $i++) {
            try {
                // logger('SyncHanetPerson remove alias from place', [
                //     'aliasID' => $client_employee['code'],
                //     'placeID' => $hanet_place_id,
                // ]);
                // $removeResponse = Http::post($end_point.'/person/removeByPlace', [
                //     'token' => $access_token,
                //     'aliasID' => $client_employee['code'],
                //     'placeID' => $hanet_place_id,
                // ]);
                // logger('SyncHanetPerson remove response', ['body' => $removeResponse->body()]);

                logger('SyncHanetPerson register alias to place', [
                    'name' => $client_employee['full_name'],
                    'url' => $avatar,
                    'aliasID' => $client_employee['code'],
                    'title' => $client_employee['title'],
                    'placeID' => $hanet_place_id,
                    'type' => 0,
                ]);
                $response = Http::post($end_point.'/person/registerByUrl', [
                    'token' => $access_token,
                    'name' => $client_employee['full_name'],
                    'url' => $avatar,
                    'aliasID' => $client_employee['code'],
                    'title' => $client_employee['title'],
                    'placeID' => $hanet_place_id,
                    'type' => 0,
                ]);

                if (!$response->successful()) {
                    logger()->debug('pushPersonToHanet failed: '.$response->body());
                }

                $body = json_decode($response->body(), true);

                if ($body['returnCode'] == -9005 || $body['returnMessage'] == 'AliasID is exists for current placeID') {
                    $removeResponse = Http::post($end_point.'/person/updateByFaceUrlByAliasID', [
                        'token' => $access_token,
                        'url' => $avatar,
                        'aliasID' => $client_employee['code'],
                        'placeID' => $hanet_place_id,
                    ]);
                    $removeResponseBody = json_decode($removeResponse->body(), true);
                    if ($removeResponseBody && isset($removeResponseBody['returnMessage']) && $removeResponseBody['returnCode'] == 1) {
                        logger('SyncHanetPerson try updateByFaceUrlByAliasID response', ['body' => $removeResponse->body()]);
                        return '';
                    }
                }
                elseif ($body['returnCode'] == -9007) {
                    logger()->debug('pushPersonToHanet failed: '.$response->body());
                    $retryResponse = Http::post($end_point.'/person/updateByFaceUrl', [
                        'token' => $access_token,
                        'url' => $avatar,
                        'aliasID' => $client_employee['code'],
                        'placeID' => $hanet_place_id,
                    ]);
                    $retryBody = json_decode($retryResponse->body(), true);
                    if ($retryBody && isset($retryBody['returnMessage']) && $retryBody['returnMessage'] === 'SUCCESS') {
                        return '';
                    }
                    logger('SyncHanetPerson retry updateByFaceUrl, but failed', ['body' => $retryResponse->body()]);
                    return $retryBody;
                }
                else {
                    logger('SyncHanetPerson register response', ['body' => $response->body()]);
                    return $body ?? 'Unknown Hanet error';
                }

                return '';
            } catch (Exception $e) {
                logger()->error('v error: '.$e->getMessage());
                return null;
            }
        }
    }
}
