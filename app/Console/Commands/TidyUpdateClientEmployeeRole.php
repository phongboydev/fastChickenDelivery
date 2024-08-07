<?php

namespace App\Console\Commands;

use App\User;
use App\Models\ClientEmployee;

use Illuminate\Console\Command;

class TidyUpdateClientEmployeeRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateclientemployeerole';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update role for client employee';

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
        $query = ClientEmployee::select('*')
                ->whereNotIn('role', ['manager', 'staff'])
                ->withTrashed();
        $query->chunkById(100, function ($employees) {
            foreach ($employees as $clientEmployee) {
                $clientEmployee->update(['role' => 'staff']);
            }
        }, 'id');
    }
}
