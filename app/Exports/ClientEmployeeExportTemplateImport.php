<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Excel;
use App\Models\ClientDepartment;
use App\Models\ClientPosition;

class ClientEmployeeExportTemplateImport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $client_id = null;

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                $activeLang = app()->getLocale();

                $templateExport = "ClientEmployeeImportTemplate/client_employee_import_{$activeLang}.xlsx";
                $path = storage_path('app/' . $templateExport);

                $extension = Excel::XLSX;

                $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                $sheetBasicInformation = $event->writer->getSheetByIndex(0);
                $sheetBasicInformation = $this->renderSheetBasicInformation($sheetBasicInformation, $activeLang);

                $sheetDepartment = $event->getWriter()->getSheetByIndex(1);
                $sheetDepartment = $this->renderSheetDepartment($sheetDepartment);

                $sheetPosition = $event->getWriter()->getSheetByIndex(2);
                $sheetPosition = $this->renderSheetPosition($sheetPosition);
            },
        ];
    }

    /**
     * Set comment into basic information sheet
     */
    public function renderSheetBasicInformation($sheet, $activeLang)
    {
        $sheet->getComment('AB2')
            ->setWidth(240)->setHeight(120)
            ->getText()->createTextRun(__('excel.tax_comment'));

        $sheet->getComment('AT2')
            ->setWidth(280)->setHeight(180)
            ->getText()->createTextRun(__('excel.insurance_comment'));
        return $sheet;
    }

    /**
     * Get list position and push to sheet position
     */
    public function renderSheetPosition($sheet)
    {
        $positions = ClientPosition::select('*')
            ->where('client_id', '=', $this->client_id)->get();

        if ($positions->count() > 0) {
            $row = 1;
            foreach ($positions as $position) {
                $row++;
                $sheet->setCellValue('A' . $row, $position->name);
                $sheet->setCellValue('B' . $row, $position->code);
            }
        }
        return $sheet;
    }

    /**
     * Get list department and push to sheet department
     */
    public function renderSheetDepartment($sheet)
    {
        $departments = ClientDepartment::select('*')
            ->where('client_id', '=', $this->client_id)->get();

        if ($departments->count() > 0) {
            $row = 1;
            foreach ($departments as $department) {
                $row++;
                $sheet->setCellValue('A' . $row, $department->department);
                $sheet->setCellValue('B' . $row, $department->code);
            }
        }
        return $sheet;
    }
}
