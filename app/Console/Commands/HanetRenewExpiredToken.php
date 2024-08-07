<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HanetSetting;
use App\Support\HanetHelper;
use Carbon\Carbon;
use App\Models\ClientLogDebug;
class HanetRenewExpiredToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hanet:renewtoken {clientCode?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew token of Hanet';

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
                $hanet = new HanetHelper();
                $data = [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('hanet.client_id'),
                    'client_secret' => config('hanet.client_secret'),
                    'refresh_token' => $hanetSetting->refresh_token
                ];
                // Store log response
                $this->storeLog('Request renew expire Hanet ', json_encode($data), 'Data request client_id : ' . $hanetSetting->client_id, $hanetSetting->client_id);
                $hanet = $hanet->refreshToken($data);
                $content = $hanet->getContents();

                // Store log response
                $this->storeLog('Response renew expire Hanet ', $content, 'Data response client_id : ' . $hanetSetting->client_id, $hanetSetting->client_id);               

                $response  = json_decode($content,true);
                if(!empty($response['refresh_token']) && !empty($response['access_token'] && !empty($response['expire']))) {
                    $hanetSetting->expiration_date = Carbon::createFromTimestamp($response['expire'])->format('Y-m-d H:i:s');
                    $hanetSetting->token = $response['access_token'];
                    $hanetSetting->refresh_token = $response['refresh_token'];
                    $hanetSetting->expire = $response['expire'];
                    $hanetSetting->status = 'Success';
                    $hanetSetting->save();
                } else {
                    $hanetSetting->status = empty($response->returnMessage)  ? 'Fail' : $response->returnMessage;
                    $hanetSetting->save();
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

        $newDateTime = Carbon::now()->addDay()->format('Y-m-d H:m:i');

        $query->where( 
            function($query) use ($newDateTime) {
                $query->where('expiration_date','<=', $newDateTime)
                    ->orWhere('expiration_date', null);
        });
        
        return $query->orderBy('expiration_date', 'desc')->get();
    }

    private function storeLog($type, $data_log, $note, $client_id) {
        $logDebug = new ClientLogDebug();
        $logDebug->client_id = $client_id;
        $logDebug->type = $type;
        $logDebug->data_log = $data_log;
        $logDebug->note = $note;
        $logDebug->save();
    }

}
