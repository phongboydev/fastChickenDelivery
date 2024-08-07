<?php

namespace App\Console\Commands;

use App\User;
use App\Models\ClientEmployee;
use App\Models\IglocalEmployee;

use Illuminate\Console\Command;

class TidyUpdateUserCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateusercode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user code from employee to user';

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
        ClientEmployee::query()
            ->chunkById(100, function ($employees) {
                foreach ($employees as $employee) {

                    if ($employee->user_id) {
                        $this->line("Processed ClientEmployee ... " . $employee->code . "|" . $employee->full_name);
                        User::where('id', $employee->user_id)->update(['code' => $employee->code]);
                    }
                }
            }, 'id');

        IglocalEmployee::query()
            ->chunkById(100, function ($employees) {
                foreach ($employees as $employee) {

                    if ($employee->user_id) {
                        $this->line("Processed IglocalEmployee ... " . $employee->code . "|" . $employee->name);
                        User::where('id', $employee->user_id)->update(['code' => $employee->code]);
                    }
                }
            }, 'id');
    }
}
