<?php

namespace App\Exports;

use App\Exceptions\CustomException;

use App\Models\CalculationSheet;
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

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;


class CalculationSheetSalaryExportFromTemplate implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $calculationSheetId;
    protected $variables;
    protected $templateExport;
    protected $templateVariable;
    protected $rows;
    protected $employeeGroupIds;

    private $total_list = 0;
    private $total_variables = 0;

    public function __construct(string $calculationSheetId, array $variables, string $templateExport, array $templateVariable, $rows, array $employeeGroupIds = [])
    {
        $this->calculationSheetId = $calculationSheetId;
        $this->variables = $variables;
        $this->templateExport = $templateExport;
        $this->templateVariable = $templateVariable;
        $this->rows = $rows;
        $this->employeeGroupIds = $employeeGroupIds;

        return $this;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                if ($this->templateExport) {

                    $path = storage_path('app/' . $this->templateExport);

                    $pathInfo = pathinfo($path);

                    if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                        return;
                    }

                    $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                    $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                    $event->writer->getSheetByIndex(0);

                    $sheet = $event->getWriter()->getSheetByIndex(0);

                    $calculationSheetId = $this->calculationSheetId;
                    $calculationSheet = CalculationSheet::find($calculationSheetId);
                    $calculationSheetClientEmployeeData = [];

                    $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::select('*')
                        ->with('calculationSheet')
                        ->with(['clientEmployee' => function ($q) {
                            if ($this->employeeGroupIds) {
                                $q->whereHas('clientEmployeeGroupAssignment', function ($sub) {
                                    $sub->whereIn('client_employee_group_id', $this->employeeGroupIds);
                                });
                            }
                        }])
                        ->whereHas('clientEmployee', function ($q) {
                            if ($this->employeeGroupIds) {
                                $q->whereHas('clientEmployeeGroupAssignment', function ($sub) {
                                    $sub->whereIn('client_employee_group_id', $this->employeeGroupIds);
                                });
                            }
                        })
                        ->join('client_employees', 'calculation_sheet_client_employees.client_employee_id', '=', 'client_employees.id')
                        ->leftJoin('calculation_sheet_template_assignments', function ($join) use ($calculationSheet) {
                            $join->on('client_employees.id', '=', 'calculation_sheet_template_assignments.client_employee_id')
                                ->where('calculation_sheet_template_assignments.template_id', $calculationSheet->calculation_sheet_template_id);
                        })
                        ->where('calculation_sheet_id', '=', $calculationSheetId)
                        ->orderBy('calculation_sheet_template_assignments.sort_by', 'ASC')
                        ->orderBy('client_employees.code', 'ASC')
                        ->get();

                    $extraData = [];

                    $totalCalculatedValue = 0;

                    if (!empty($calculationSheetClientEmployeeLists)) {

                        foreach ($calculationSheetClientEmployeeLists as $cIndex => $item) {

                            if (isset($item['clientEmployee']) && $item['clientEmployee']) {

                                // Initial get client information
                                if ($cIndex == 0) {
                                    $extraData[] = Client::select('*')->where('id', $item['clientEmployee']->client_id)->first()->toArray();
                                    $extraData[] = $item['calculationSheet']->toArray();
                                }

                                $calculationSheetClientEmployeeDataTmp = [
                                    'NO' => $cIndex + 1,
                                    'CODE' => $item['clientEmployee']->code,
                                    'NAME' => $item['clientEmployee']->full_name,
                                    'WORKPLACE' => $item['clientEmployee']->workplace,
                                    'ONBOARD_DATE' => $item['clientEmployee']->onboard_date,
                                    'MST_CODE' => $item['clientEmployee']->mst_code,
                                ];

                                $calculationSheetVariables = CalculationSheetVariable::select('*')
                                    ->where('calculation_sheet_id', '=', $item['calculationSheet']->id)
                                    ->where('client_employee_id', '=', $item['clientEmployee']->id)
                                    ->get();

                                // đổ biến của từng NV vào mảng
                                if (!empty($calculationSheetVariables)) {
                                    foreach ($calculationSheetVariables as $calculationSheetVariable) {
                                        $calculationSheetClientEmployeeDataTmp[strtoupper($calculationSheetVariable['variable_name'])] = $calculationSheetVariable['variable_value'];
                                    }
                                }

                                $calculationSheetClientEmployeeDataTmp['SALARY'] = $item['calculated_value'];

                                // đổ biến của từng NV vào mảng
                                $calculationSheetClientEmployeeData[] = $calculationSheetClientEmployeeDataTmp;

                                $totalCalculatedValue += $item['calculated_value'];
                            }
                        }

                        if (isset($this->templateVariable['$LOOP_START'][0][1])) {
                            // Set ô LOOP_START thành rỗng
                            for ($i = $this->templateVariable['$LOOP_START'][0][1]; $i < 100; $i++) {
                                $colIndex = Coordinate::stringFromColumnIndex($i + 1);
                                $pos = $colIndex . ($this->templateVariable['$LOOP_START'][0][0] + 2);
                                $sheet->setCellValue($pos, null);
                            }
                        } else {
                            throw new CustomException(
                                'Thiếu $LOOP_START',
                                'ValidationException'
                            );
                        }

                        // Set trắng các ô biến
                        foreach ($this->templateVariable as $cIndex => $row) {
                            foreach ($row as $cRow) {
                                $colIndex = Coordinate::stringFromColumnIndex($cRow[1] + 1);
                                $sheet->setCellValue($colIndex . ($cRow[0] + 1), null);
                            }
                        }

                        $totalRow = [];
                        $maxRowIndex = 0;
                        $maxColumnIndex = 0;

                        // Add empty row for data insert
                        // TODO future work
                        // $sheet->getDelegate()->insertNewRowBefore($this->templateVariable['$LOOP_START'][0][0]+1,
                        //     count($calculationSheetClientEmployeeData));

                        // Loop qua mảng biến của từng NV
                        foreach ($calculationSheetClientEmployeeData as $cIndex => $cRow) {
                            // Đổ value vào từng cột có ký hiệu $
                            foreach ($cRow as $cKey => $value) {

                                $c = '$' . strtoupper($cKey);

                                if (isset($this->templateVariable[$c]) && !empty($this->templateVariable[$c])) {

                                    $totalRow[$cKey] = 0;

                                    foreach ($this->templateVariable[$c] as $dRow) {
                                        // colIndex bắt đầ từ 1, 1=A, 2=B
                                        $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);

                                        $sheet->setCellValue($colIndex . ($dRow[0] + $cIndex), $value);

                                        // Tìm cột lớn nhất
                                        if ($dRow[1] > $maxColumnIndex) {
                                            $maxColumnIndex = $dRow[1];
                                        }
                                        $maxRowIndex = ($dRow[0] + $cIndex);
                                    }
                                }
                            }

                            // Copy style off
                            // if (!empty($this->rows)) {
                            //     $newRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 1 + $cIndex;
                            //
                            //     // tô màu cho cell
                            //     for ($cellIndex = 0; $cellIndex <= $maxColumnIndex; $cellIndex++) {
                            //         $column = Coordinate::stringFromColumnIndex($cellIndex + 1);
                            //
                            //         $originRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 2;
                            //
                            //         $orginStyle = $sheet->getDelegate()->getStyle($column . $originRowIndex);
                            //
                            //         $range = $column . $newRowIndex . ":" . $column . $newRowIndex;
                            //
                            //         $sheet->getDelegate()->duplicateStyle($orginStyle, $range);
                            //
                            //         echo  memory_get_usage() . "\n";
                            //
                            //         $h = $sheet->getRowDimension($this->templateVariable['$LOOP_START'][0][0] + 1)->getRowHeight();
                            //
                            //         $sheet->getRowDimension($newRowIndex)->setRowHeight($h);
                            //     }
                            // }
                        }

                        if (!empty($calculationSheetClientEmployeeData)) {

                            foreach ($calculationSheetClientEmployeeData as $items) {
                                foreach ($items as $c => $item) {
                                    if (is_numeric($item) && isset($totalRow[$c])) {
                                        $totalRow[$c] += $item;
                                    }
                                }
                            }
                        }

                        if (!empty($totalRow)) {

                            foreach ($totalRow as $cKey => $value) {

                                foreach ($this->templateVariable['$' . $cKey] as $dRow) {

                                    if (!in_array($cKey, ['NO', 'CODE', 'NAME', 'S_POSITION'])) {

                                        $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);
                                        $rowIndex = $maxRowIndex + 1;

                                        $sheet->setCellValue($colIndex . $rowIndex, $value);
                                    }
                                }
                            }
                        }

                        $templateExtra = array_slice($this->templateVariable, 0, array_search('$LOOP_START', array_keys($this->templateVariable)), true);

                        foreach ($extraData as $items) {

                            foreach ($items as $cKey => $value) {

                                $c = '$' . strtoupper($cKey);

                                if (isset($templateExtra[$c]) && !empty($templateExtra[$c])) {

                                    foreach ($templateExtra[$c] as $dRow) {

                                        $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);

                                        $sheet->setCellValue($colIndex . ($dRow[0] + 1), $value);
                                    }
                                }
                            }
                        }
                    }
                }
            },
        ];
    }
}
