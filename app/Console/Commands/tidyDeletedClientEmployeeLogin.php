<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\User;
use Illuminate\Console\Command;

class tidyDeletedClientEmployeeLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:clientEmployeeDeletedLogin';

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
        ClientEmployee::onlyTrashed()->chunk(100, function ($items) {
            foreach ($items as $item) {
                $user = User::query()->where("id", $item->user_id)->first();
                if ($user) {
                    $this->info("Clean up ... " . $user->username . " | " . $user->name);
                    $user->delete();
                }
            }
        });
    }
}
