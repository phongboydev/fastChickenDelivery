<?php

namespace App\Console\Commands;

use App\Models\LeaveCategory;
use Illuminate\Console\Command;

class UpdateOldDataOfSettingLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'updateOldDataOfSettingLeave:trigger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        LeaveCategory::where([
            'type' => 'authorized_leave',
            'code' => 'NPN'
        ])->chunkById(100, function ($items) {
            foreach ($items as $item) {
                $item->sub_type = 'year_leave';
                $item->save();
            }
        });
        return 0;
    }
}
