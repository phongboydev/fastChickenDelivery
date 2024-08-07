<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HanetSetting;
use App\Support\HanetHelper;
use App\Models\HanetPlace;
use App\Models\ClientDepartmentHanet;

class HanetSyncDepartment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hanet:SyncDepartment {clientCode?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync department from Hanet to Terra';

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
                $this->syncDepartmentHanet($hanetSetting);
            }
        }
        return 0;
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

    /**
     * Sync data departments from Hanet
     */

     private function syncDepartmentHanet($hanetSetting) {
        $places = HanetPlace::where('client_id', $hanetSetting->client_id)
                ->get()
                ->toArray();
        foreach($places as $place) { 
            $page = 1;
            $size = 500;
            $departments = [];
            $data = [ 
                'token' => $hanetSetting->token,
                'placeID' => $place['hanet_place_id'],
                'size' => $size,
                'page'=> $page
            ];
            $hanet = new HanetHelper();
            $response = $hanet->departmentList($data);
            $response = json_decode($response->getContents(), true);
            if(empty($response['data'])) {
                break;
            }
            if(isset($response['data']['hits']) && count($response['data']['hits']) > 0 ) {
                $departments = $response['data']['hits'];
                foreach($departments as $department) {
                    ClientDepartmentHanet::updateOrCreate( 
                        [
                            'hanet_department_id' => $department['id']                            
                        ],
                        [
                            'hanet_place_id' => $place['hanet_place_id'],
                            'name' => $department['name'],
                            'desc' => $department['desc']
                        ]);
                }
            }

        }
     }
}
