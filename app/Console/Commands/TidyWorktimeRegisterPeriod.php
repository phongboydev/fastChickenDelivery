<?php

namespace App\Console\Commands;

use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TidyWorktimeRegisterPeriod extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:workTimeRegisterPeriod';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

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
     * @return mixed
     */
    public function handle()
    {
        $arrWorktimeRegister = WorktimeRegister::query()
                                               ->whereIn("type", ["overtime_request"])
                                               ->doesntHave('workTimeRegisterPeriod')
                                               ->get();
        foreach ($arrWorktimeRegister as $key => $value) {
            $this->info("Process WorkTimeRegister Type=" . $value->type . ", ID=" . $value->id);
            $startTime = Carbon::parse($value->start_time);
            $endTime = Carbon::parse($value->end_time);
            // same date
            if ($startTime->toDateString() == $endTime->toDateString()) {
                WorkTimeRegisterPeriod::create([
                    'worktime_register_id' => $value->id,
                    'date_time_register' => $startTime->toDateString(),
                    'type_register' => true,
                    'start_time' => explode(" ", $value->start_time)[1],
                    'end_time' => explode(" ", $value->end_time)[1],
                ]);
            } else {
                $now = $startTime->clone();
                WorkTimeRegisterPeriod::create([
                    'worktime_register_id' => $value->id,
                    'date_time_register' => $now->toDateString(),
                    'type_register' => true,
                    'start_time' => explode(" ", $value->start_time)[1],
                    'end_time' => "23:59:59",
                ]);
                $now->addDay();
                while ($now->toDateString() != $endTime->toDateString()) {
                    WorkTimeRegisterPeriod::create([
                        'worktime_register_id' => $value->id,
                        'date_time_register' => $now->toDateString(),
                        'type_register' => false,
                        'start_time' => "00:00:00",
                        'end_time' => "23:59:59",
                    ]);
                    $now->addDay();
                }
                WorkTimeRegisterPeriod::create([
                    'worktime_register_id' => $value->id,
                    'date_time_register' => $now->toDateString(),
                    'type_register' => true,
                    'start_time' => "00:00:00",
                    'end_time' => explode(" ", $value->end_time)[1],
                ]);
            }
        }
    }
}
