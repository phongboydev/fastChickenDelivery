<?php

namespace App\Console\Commands;

use App\Models\CalculationSheetExportTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PayrollTemplateMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:calculationSheetTemplate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move payroll template file to media';

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
        CalculationSheetExportTemplate::select('*')
            ->chunkById(100, function($calculationSheetTemplates) {
                foreach ($calculationSheetTemplates as $calculationSheetTemplate) {

                    $this->line("Checking ... " . $calculationSheetTemplate->name);

                    $media = $calculationSheetTemplate->getMedia('CalculationSheetExportTemplate');

                    if($media->isEmpty() && !Storage::missing($calculationSheetTemplate->file_name)) {

                        $this->line("Moving ... " . $calculationSheetTemplate->name);

                        $calculationSheetTemplate->addMediaFromDisk($calculationSheetTemplate->file_name, 'minio')
                                    ->toMediaCollection('CalculationSheetExportTemplate', 'minio');
                    }
                }
            }, 'id');
    }
}
