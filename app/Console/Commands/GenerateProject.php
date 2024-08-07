<?php

namespace App\Console\Commands;

use App\Models\AssignmentProject;
use App\Models\Client;
use Illuminate\Console\Command;

class GenerateProject extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:project';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Assignment Project (Iglocal)';

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
    public function handle(): int
    {
        Client::query()
              ->chunk(100, function ($clients) {
                  foreach ($clients as $client) {
                      $this->info("Process ... ".$client->code." ".$client->company_name);
                      if (!$client->assignmentProject) {
                          AssignmentProject::createProjectForClient($client);
                      }
                  }
              });
        return 0;
    }
}
