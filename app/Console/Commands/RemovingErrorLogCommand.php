<?php

namespace App\Console\Commands;

use App\Models\ErrorLog;
use Illuminate\Console\Command;

class RemovingErrorLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'errorLog:remove {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unused error log';

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
        $date = $this->argument("date") . ' 23:59:59';
        ErrorLog::where('created_at', '<=', $date)->delete();

        return 0;
    }
}
