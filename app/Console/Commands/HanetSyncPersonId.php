<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HanetSetting;
use App\Support\HanetHelper;
use App\Models\HanetPerson;
use App\Models\HanetPlace;
use App\Models\HanetPlacePerson;

class HanetSyncPersonId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hanet:SyncPersonId {clientCode?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Person ID to Terra';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $clientCode = $arguments['clientCode'];
        $hanetSettings = $this->getListHanetSetting( $clientCode );
        foreach ($hanetSettings as $hanetSetting) {
            if( $hanetSetting->client_id ) {
                $this->syncPersonIdHanet($hanetSetting);
            }
        }
        return 0;
    }

    private function syncPersonIdHanet( $hanetSetting ) {
        
        $places = HanetPlace::where('client_id', $hanetSetting->client_id)
                ->get()
                ->toArray();

        foreach($places as $place) {     
            $page = 1;
            $size = 500;
            $persons = [];
            do {            
                $data = [ 
                    'token' => $hanetSetting->token,
                    'placeID' => $place['hanet_place_id'],
                    'size' => $size,
                    'page'=> $page
                ];
                $hanet = new HanetHelper();
                $response = $hanet->getListByPlace($data);

                $response = json_decode($response->getContents(), true);
                if(empty($response['data'])) {
                    break;
                }
                $persons = array_merge($persons, $response['data']);
                $page++;                
            } while (true);

            $HanetPersons = HanetPerson::where('client_id', $hanetSetting->client_id)->get();
            foreach($HanetPersons as $hanetPerson){
                foreach($persons as $person) {                    
                    if(!empty($hanetPerson->alias_id) & !empty($person['aliasID']) &  $hanetPerson->alias_id == $person['aliasID']){
                        $hanetPlacePersons = HanetPlacePerson::where('hanet_person_id',$hanetPerson->id)->get();
                        foreach($hanetPlacePersons as $hanetPlacePerson) {
                            if($person['placeID'] == $hanetPlacePerson->hanetPlace->hanet_place_id) {
                                $hanetPlacePerson->person_id = $person['personID'];
                                $hanetPlacePerson->save();
                            }
                        }
                    }
                }            
            }
        }
        
    }

    /**
     * Get list hanet need expire
     */
    private function getListHanetSetting($clientCode = '') {
        $query = HanetSetting::query();        
        $query->whereHas('client', function ($subQuery) use ($clientCode) {
            if ($clientCode) {
                $subQuery->where('code', $clientCode);
            }
        });
        return $query->get();
    }
}
