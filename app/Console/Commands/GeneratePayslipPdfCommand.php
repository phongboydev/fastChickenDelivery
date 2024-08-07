<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePdfJob;
use App\Models\CalculationSheetClientEmployee;
use App\Pdfs\CalculationSheetClientEmployeeHtmlToPdf;
use Illuminate\Console\Command;

class GeneratePayslipPdfCommand extends Command
{

    protected $signature = 'generate:payslip-pdf {--print : print existed PDF path only, no generate} {--f|force} {--s|sync : Force run in sync, otherwise it will be run in default queue} {--c|calculation-sheet-id= : ID of calculation sheet} {--a|after= : Only process payslip that after date yyyy-mm-dd} {id?}';

    protected $description = 'Generate payslip pdf for missing CalculationSheetClientEmployee';

    public function handle()
    {
        $force = $this->option("force");
        $sync = $this->option("sync");
        $print = $this->option("print");
        $after = $this->option("after");
        $calculationSheetId = $this->option("calculation-sheet-id");
        $id    = $this->argument("id");
        $query = CalculationSheetClientEmployee::query();
        $query->has('client')  // if client is deleted, generate will yield error
              ->isCompleted(); // only calculated payslip
        if (!$force) {
            $query->notHasPdf();
        }
        if ($after) {
            $query->where('created_at', ">=", $after);
        }
        if ($id) {
            $query->where("id", $id);
        }
        if ($calculationSheetId) {
            $query->where('calculation_sheet_id', $calculationSheetId);
        }
        $query->chunk(100, function ($sheets) use ($sync, $print) {
            foreach ($sheets as $sheet) {
                /** @var CalculationSheetClientEmployee $sheet */
                $pdf = new CalculationSheetClientEmployeeHtmlToPdf($sheet);
                if (!$print) {
                    if (!$sync) {
                        dispatch(new GeneratePdfJob($pdf));
                    } else {
                        dispatch_sync(new GeneratePdfJob($pdf));
                    }
                    $this->line("Generate ... " . $sheet->id);
                } else {
                    $this->line("Current PDF path ... {$sheet->id} : " . $sheet->pdf_path);
                }
            }
        });
        $this->line("Done.");
    }
}
