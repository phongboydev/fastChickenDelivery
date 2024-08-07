<?php

namespace App\Console\Commands;

use App\Models\CalculationSheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PayrollFileMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:calculationSheetMedia';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move payroll file to media';

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
        CalculationSheet::select('*')->with('client')
            ->chunkById(100, function($calculationSheets) {
                foreach ($calculationSheets as $calculationSheet) {

                    $this->line("Processed ... " . $calculationSheet->name);

                    $client = $calculationSheet->client;

                    $excelFile = 'CalculationSheetExport/' . $calculationSheet->id . '/' . $client['code'] . '_SALARY_REPORT_' . $calculationSheet->month . '_' . $calculationSheet->year . '.xlsx';
                    $csvFile = 'CalculationSheetExport/' . $calculationSheet->id . '/' . $client['code'] . '_SALARY_' . $calculationSheet->month . '_' . $calculationSheet->year . '.csv';

                    if(!Storage::missing($excelFile)){
                        $calculationSheet->addMediaFromDisk($excelFile, 'minio')
                                    ->toMediaCollection('CalculationSheet', 'minio');
                    }

                    if(!Storage::missing($csvFile)){
                        $calculationSheet->addMediaFromDisk($csvFile, 'minio')
                                    ->toMediaCollection('CalculationSheet', 'minio');
                    }

                }
            }, 'id');
    }
}
