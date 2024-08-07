<?php

namespace App\Console\Commands;

use App\Models\PayrollAccountantExportTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TidyPayrollAccountantExportTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:payrollAccountantExportTemplate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move Payroll Accountant Export Template file to media';

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
        PayrollAccountantExportTemplate::select('*')
            ->chunkById(100, function($payrollAccountantExportTemplates) {
                foreach ($payrollAccountantExportTemplates as $payrollAccountantExportTemplate) {

                    $this->line("Checking ... " . $payrollAccountantExportTemplate->name);

                    $media = $payrollAccountantExportTemplate->getMedia('CalculationSheetExportTemplate');

                    if($media->isEmpty() && $payrollAccountantExportTemplate->file_name && !Storage::missing($payrollAccountantExportTemplate->file_name)) {

                        $this->line("Moving ... " . $payrollAccountantExportTemplate->name);

                        $payrollAccountantExportTemplate->addMediaFromDisk($payrollAccountantExportTemplate->file_name, 'minio')
                                    ->toMediaCollection('PayrollAccountantExportTemplate', 'minio');
                    }
                }
            }, 'id');
    }
}
