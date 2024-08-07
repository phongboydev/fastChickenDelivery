<?php

namespace App\Console\Commands;

use App\Models\OvertimeCategory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateOvertimeCategoryEveryYearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:createOvertimeCategoryEveryYear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'In the last day of the year by end date, create a new overtime category for the next year based on the start_time and end_time';

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
        $now = now();
        $overtimeCategories = OvertimeCategory::where([
            ['start_date', '<=', $now->format('m-d')],
            ['end_date', '>=', $now->format('m-d')]
        ])->get();
        foreach ($overtimeCategories as $overtimeCategory) {
            if ($now->isBefore(Carbon::parse($overtimeCategory->end_date))) {
                continue;
            }

            // Create new overtime category for the next year
            $newOvertimeCategory = $overtimeCategory->replicate();
            $newOvertimeCategory->id = Str::uuid();
            $newOvertimeCategory->start_date = Carbon::parse($overtimeCategory->start_date)->addYear()->format('Y-m-d');
            $newOvertimeCategory->end_date = Carbon::parse($overtimeCategory->end_date)->addYear()->format('Y-m-d');
            $newOvertimeCategory->year = $overtimeCategory->year + 1;
            $newOvertimeCategory->save();
        }

        return 1;
    }
}
