<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Approve;

class CleanApproves extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'approve:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean approves that have user is null';

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
        Approve::query()
              ->chunk(100, function ($approves) {
                  foreach ($approves as $approve) {
                      if (!$approve->creator) {
                        $approve->delete();      
                        $this->info("Process ... ".$approve->creator_id." ".$approve->type);
                      }
                  }
              });

        return Command::SUCCESS;
    }
}
