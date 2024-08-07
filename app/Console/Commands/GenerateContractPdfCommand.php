<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePdfJob;
use App\Models\CalculationSheetClientEmployee;
use App\Models\Contract;
use App\Pdfs\CalculationSheetClientEmployeeHtmlToPdf;
use App\Pdfs\ContractDocToPdf;
use Illuminate\Console\Command;

class GenerateContractPdfCommand extends Command
{

    protected $signature = 'generate:contract-pdf {--print : print existed PDF path only, no generate} {--f|force} {--s|sync : Force run in sync, otherwise it will be run in default queue} {--c|contract-id= : ID of contract} {--a|after= : Only process contract that after date yyyy-mm-dd} {id?}';

    protected $description = 'Generate contract PDF from commandline';

    public function handle()
    {
        $force = $this->option("force");
        $sync = $this->option("sync");
        $print = $this->option("print");
        $after = $this->option("after");
        $contractId = $this->argument("id");
        $query = Contract::query();
        $query->has('client');  // if client is deleted, generate will yield error
        if (!$force) {
            $query->notHasPdf();
        }
        if ($after) {
            $query->where('created_at', ">=", $after);
        }
        if ($contractId) {
            $query->where('id', $contractId);
        }

        $query->chunk(100, function ($sheets) use ($sync, $print) {
            foreach ($sheets as $sheet) {
                /** @var Contract $sheet */
                $pdf = new ContractDocToPdf($sheet);
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
