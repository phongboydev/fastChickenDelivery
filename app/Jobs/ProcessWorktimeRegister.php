<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use App\Models\WorktimeRegister;

class ProcessWorktimeRegister implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    public function __construct($id)
    {
        $this->id = $id; 
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $wtr = WorktimeRegister::find($this->id); 

        if ($wtr) {
            $startTime = $wtr->start_time;
            $endTime = $wtr->end_time;
            $periods = $wtr->periods;

            foreach ($periods as $p) {
                $pDate = $p->date_time_register;
                $pStartTime = $p->type_register == '0' ? "00:00:00" : $p->start_time;
                $pEndTime = $p->type_register == '0' ? "23:59:59" : $p->end_time;

                $pStartDateTime = $pDate . " " . $pStartTime;

                $pEndDateTime =  $p->next_day ? Carbon::parse($pDate)->addDays()->format('Y-m-d') . ' ' .  $p->end_time : $pDate . " " . $pEndTime;

                if (strtotime($startTime) > strtotime($pStartDateTime)) {
                    $startTime = $pStartDateTime;
                } 

                if (strtotime($endTime) < strtotime($pEndDateTime)) {
                    $endTime = $pEndDateTime;
                } 
            }

            $wtr->start_time = $startTime;
            $wtr->end_time = $endTime;
            $wtr->save();
        }
    }

    public function uniqueId()
    {
        return $this->id;
    }
}
