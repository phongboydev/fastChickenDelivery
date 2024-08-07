<?php

namespace App\Console\Commands;

use App\User;
use App\Models\Client;
use App\Models\ClientWorkflowSetting;

use Illuminate\Console\Command;

class TidyUpdateClientsType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateclientstype';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update client type';

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
      ClientWorkflowSetting::query()
            ->chunk(100, function ($clients) {
                foreach ($clients as $client) {
                    if ($client->client_id) {
                        $this->line("Processed Client ... " . $client->client_id);
                        $type = $client->enable_create_payroll == 1 ? 'system' : 'outsourcing';
                        Client::where('id', $client->client_id)->update(['client_type' => $type]);
                    }
                }
            });
    }
}
