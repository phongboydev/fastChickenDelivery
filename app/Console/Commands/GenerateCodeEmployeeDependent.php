<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ClientEmployeeDependent;

class GenerateCodeEmployeeDependent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:code-employee-dependent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command gernal code employee dependent same time created_at';

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
        try {
            DB::beginTransaction();
            $items = ClientEmployeeDependent::all();
            //update publish_time column
            foreach($items as $item){
                $item->code_dependent = strtotime($item->created_at);
                $item->save();
            }
            logger('general code dependent');
            DB::commit();
           
            //send output to the console
            $this->info('Success!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error($e->getMessage());
        }
    }
}
