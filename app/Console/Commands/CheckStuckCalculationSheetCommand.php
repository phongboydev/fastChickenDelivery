<?php

namespace App\Console\Commands;

use App\Events\CalculationSheetReadyEvent;
use App\Listeners\CalculationSheetReadyListener;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use Illuminate\Console\Command;

class CheckStuckCalculationSheetCommand extends Command
{

    protected $signature = 'check:stuck-calculation-sheet';

    protected $description = 'Check if there is any stuck calculation sheet.';

    public function handle(): void
    {
        $this->info('Checking stuck calculation sheet ...');
        // look for CalculationSheet with status == 'creating'
        CalculationSheet::where('status', 'creating')
            ->where('created_at', '<', now()->subMinutes(5))
            ->get() // số lượng dữ liệu trả về ít dùng get() còn nhiều thì dùng chunk()
            ->each(function (CalculationSheet $calculationSheet) {
                $this->info('Found stuck calculation sheet ' . $calculationSheet->name);
                $hasNotReadyRecord = CalculationSheetClientEmployee::where("calculation_sheet_id", $calculationSheet->id)
                    ->notReady()
                    ->exists();
                if (!$hasNotReadyRecord) {
                    $listener = (new CalculationSheetReadyListener());
                    $listener->handle(new CalculationSheetReadyEvent($calculationSheet));
                }
            });
    }
}
