<?php

namespace App\Console\Commands;

use App\User;
use Illuminate\Console\Command;

class TidyPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh / recalculate permissions for all users';

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
        User::query()
            ->chunk(100, function($users) {
                foreach ($users as $user) {
                    /** @var User $user */
                    $this->line("Processed ... " . $user->id . "|" . $user->name);
                    $user->refreshPermissions();
                }
            });
    }
}
