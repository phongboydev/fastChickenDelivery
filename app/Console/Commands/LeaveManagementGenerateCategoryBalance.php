<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Support\LeaveHelper;
use Illuminate\Console\Command;

class LeaveManagementGenerateCategoryBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-management:generate-category-balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate category balance';

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
        ClientEmployee::withoutEvents(function () {
            ClientEmployee::chunk(100, function ($employees) {
                foreach ($employees as $employee) {
                    $this->info("Process ... " . $employee->id);
                    $employee->update(['leave_balance' => json_encode(LeaveHelper::LEAVE_BALANCES)]);
                }
            });
        });

        return 0;
    }
}
