<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\CalculationSheet;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;

class GetFileFromMedia extends Command
{
    /**
     * The name and signature of the console command.
     * type ['CalculationSheet']
     * @var string
     */
    protected $signature = 'General:get-files {clientCode} {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get files from media';

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
        $clientCode = $this->argument('clientCode');
        $type = $this->argument('type');
        $client = Client::where('code', $clientCode)->first();
        $calculatorSheets = CalculationSheet::where('client_id', $client->id)->whereIn('status', ['paid','client_approved'])->get();
        foreach($calculatorSheets as $calculatorSheet) {
            if(!empty($calculatorSheet->excelPath)){
                foreach($calculatorSheet->media as $file) {
                    $this->line($calculatorSheet->excelPath);
                    if(!empty($file->file_name)) {
                        Storage::disk('local')->put($file->file_name, file_get_contents($calculatorSheet->excelPath));  
                        $path = Storage::path($file->file_name);
                        $this->line($path);
                    }                    
                }

            }            
        }

        return 0;
    }
}
