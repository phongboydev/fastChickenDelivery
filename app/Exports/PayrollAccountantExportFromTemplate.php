<?php

namespace App\Exports;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\Client;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Excel;

use Maatwebsite\Excel\Files\LocalTemporaryFile;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class PayrollAccountantExportFromTemplate implements WithEvents, ShouldAutoSize
{

    use Exportable;

    protected $calculationSheetId;
    protected $variables;
    protected $translates;
    protected $templateExport;
    protected $templateVariable;

    public function __construct(string $calculationSheetId, array $variables, array $translates, string $templateExport, array $templateVariable)
    {
        $this->calculationSheetId = $calculationSheetId;
        $this->variables = $variables;
        $this->translates = $translates; // TODO dead code
        $this->templateExport = $templateExport;
        $this->templateVariable = $templateVariable;

        return $this;
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {
                if ($this->templateExport) {
                    $path = Storage::disk("local")->path($this->templateExport);

                    $event->writer->reopen(new LocalTemporaryFile($path), Excel::XLSX);

                    $event->writer->getSheetByIndex(0);

                    $sheet = $event->getWriter()->getSheetByIndex(0);

                    $extraData = $this->variables;

                    $templateExtra = $this->templateVariable;

                    foreach ($extraData as $item) {
                        $c = '$' . strtoupper($item['variable_name']);
                        $value = isset($item['value']) ? $item['value'] : 0;

                        if (isset($templateExtra[$c]) && !empty($templateExtra[$c])) {
                            foreach ($templateExtra[$c] as $dRow) {
                                $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);

                                $sheet->setCellValue($colIndex . ($dRow[0] + 1), $value);
                            }
                        }
                    }
                }
            },
        ];
    }
}
