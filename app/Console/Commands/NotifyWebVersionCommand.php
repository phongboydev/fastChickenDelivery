<?php

namespace App\Console\Commands;

use App\Models\WebVersion;
use App\Notifications\WebVersionNotification;
use App\User;
use Illuminate\Console\Command;

class NotifyWebVersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:web-version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify new web features to all users';

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
        $webVersions = WebVersion::with('webFeatureSliders')->where([
            'notified_date' => date('Y-m-d'), 
            'is_active' => 1
        ])->get();
        User::chunkById(1000, function($users) use($webVersions) {
            foreach($users as $user) {
                if(!$user->is_internal){
                    $user->notify(new WebVersionNotification($webVersions));
                }
            }
        }, 'id');
    }
}
