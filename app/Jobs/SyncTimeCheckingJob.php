<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\SyncTimesheetTmp;
use Illuminate\Support\Carbon;
use App\Support\PeriodHelper;
use Exception;
class SyncTimeCheckingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id;
    public $status;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($syncTimesheetTmpId, $status)
    {
        //
        $this->id = $syncTimesheetTmpId;
        $this->status = (int) $status;

    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    // public function uniqueId()
    // {
    //     return $this->id;
    // }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        if($this->id) {
            
            try { 
                $timeChecking  = new SyncTimesheetTmp();
                $timeChecking = $timeChecking->where('id', $this->id)->where('status',$this->status)
                ->with('clientEmployee')
                ->first();
                
                if($timeChecking && $timeChecking->client_employee_id) {
                    $clientEmployee = $timeChecking->clientEmployee;
                    $listTimeCheckings  = json_decode($timeChecking->data);
                    foreach($listTimeCheckings as $item) {
                        if(!empty($item->datetimeStart) && !empty($item->datetimeEnd)){
                            $datetimeStart = Carbon::parse($item->datetimeStart);
                            $clientEmployee->checkTimeAuto($datetimeStart->toDateString(), PeriodHelper::getHourString($datetimeStart));
                            $datetimeEnd = Carbon::parse($item->datetimeEnd);
                            $clientEmployee->checkTimeAuto($datetimeEnd->toDateString(), PeriodHelper::getHourString($datetimeEnd));
                            $timeChecking->status = 2;
                            $timeChecking->save();
                        }
                    }
                    
                } else {
                    $timeChecking->message_error = 'client_employee_id not exist';
                    $timeChecking->save();
                }

            }  catch (Exception $e)  {
                $timeSyncTmp =  SyncTimesheetTmp::find($this->id);
                $timeSyncTmp->message_error = $e->getMessage();
                $timeSyncTmp->save();
            }  
            
            logger(['job id'.$this->id]);
        }
        
    }
}
