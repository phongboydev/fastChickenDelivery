<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
class CheckTimezoneSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:checkTimezoneSchedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check timezone';

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
        $timezone = date_default_timezone_get();

        logger("The current server timezone is: " . $timezone);

        logger('CheckTimezoneSchedule: ' . Carbon::today());
    }
}
