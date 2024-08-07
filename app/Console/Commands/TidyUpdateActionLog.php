<?php

namespace App\Console\Commands;

use App\User;
use App\Models\ActionLog;
use App\Models\Client;

use Illuminate\Console\Command;

class TidyUpdateActionLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateactionlog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update field client_id';

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
        ActionLog::query()
            ->chunkById(100, function ($actionlogs) {
                foreach ($actionlogs as $actionlog) {
                    if ($actionlog->client_code) {
                        $client = Client::where('code', $actionlog->client_code)->first();
                        if (!empty($client)) {
                            ActionLog::where('client_code', $actionlog->client_code)->update(['client_id' => $client['id']]);
                        }
                    }
                }
            }, 'id');
    }
}
