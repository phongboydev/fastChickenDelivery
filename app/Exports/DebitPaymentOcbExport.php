<?php

namespace App\Exports;

use App\Exceptions\HumanErrorException;
use App\Support\ConvertHelper;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;

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


class DebitPaymentOcbExport implements WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $calculationSheetId;
    protected $variables;
    protected $templateExport = 'DebitPayment/ocb.xlsx';
    protected $templateVariable;
    protected $rows;

    private $total_list = 0;
    private $total_variables = 0;

    protected $payroll;

    public function __construct(CalculationSheet $payroll, $templateVariable)
    {
        $this->payroll = $payroll;
        $this->templateVariable = $templateVariable;
    }

    public function registerEvents(): array
    {

        return [
            BeforeExport::class => function (BeforeExport $event) {

                $path = storage_path('app/' . $this->templateExport);

                $pathInfo = pathinfo($path);

                if (!in_array($pathInfo['extension'], ['xls', 'xlsx'])) {
                    return;
                }

                $extension = $pathInfo['extension'] == 'xls' ? Excel::XLS : Excel::XLSX;

                $event->writer->reopen(new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), $extension);

                $event->writer->getSheetByIndex(0);

                $sheet = $event->getWriter()->getSheetByIndex(0);

                $calculationSheetId = $this->payroll->id;

                $calculationSheetClientEmployeeData = [];

                $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::select('*')
                    ->with('calculationSheet')
                    ->with('clientEmployee')
                    ->join('client_employees', 'calculation_sheet_client_employees.client_employee_id', '=', 'client_employees.id')
                    ->where('calculation_sheet_id', '=', $calculationSheetId)
                    ->orderBy('client_employees.full_name', 'ASC')
                    ->get();

                $client = $this->payroll->client;
                $payrollDate = $this->payroll->month . '/' . $this->payroll->year;

                if (!empty($calculationSheetClientEmployeeLists)) {

                    foreach ($calculationSheetClientEmployeeLists as $cIndex => $item) {

                        if (isset($item['clientEmployee']) && $item['clientEmployee']) {

                            $paymentDetail = "Kì thanh toán tháng {$payrollDate} {$client->company_name}";

                            $calculationSheetClientEmployeeDataTmp = [
                                'FULL_NAME' => ConvertHelper::charsetConversion($item['clientEmployee']->full_name),
                                'BANK_ACCOUNT_NUMBER' => $item['clientEmployee']->bank_account_number,
                                'SALARY' => $item['calculated_value'],
                                'BANK_CODE' => $item['clientEmployee']->bank_code,
                                'BANK_ACCOUNT' => $item['clientEmployee']->bank_account,
                                'BANK_NAME' => $item['clientEmployee']->bank_name,
                                'PAYMENT_DETAIL' => ConvertHelper::charsetConversion($paymentDetail),
                            ];

                            $calculationSheetClientEmployeeData[] = $calculationSheetClientEmployeeDataTmp;
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
                        throw new HumanErrorException('Thiếu $LOOP_START');
                    }

                    // Set trắng các ô biến
                    foreach ($this->templateVariable as $cIndex => $row) {
                        foreach ($row as $cRow) {
                            $colIndex = Coordinate::stringFromColumnIndex($cRow[1] + 1);
                            $sheet->setCellValue($colIndex . ($cRow[0] + 1), null);
                        }
                    }

                    $maxColumnIndex = 0;

                    // Loop qua mảng biến của từng NV
                    foreach ($calculationSheetClientEmployeeData as $cIndex => $cRow) {
                        // Đổ value vào từng cột có ký hiệu $
                        foreach ($cRow as $cKey => $value) {

                            $c = '$' . strtoupper($cKey);

                            if (isset($this->templateVariable[$c]) && !empty($this->templateVariable[$c])) {

                                foreach ($this->templateVariable[$c] as $dRow) {
                                    // colIndex bắt đầ từ 1, 1=A, 2=B
                                    $colIndex = Coordinate::stringFromColumnIndex($dRow[1] + 1);

                                    $sheet->setCellValue($colIndex . ($dRow[0] + $cIndex + 1), $value);

                                    // Tìm cột lớn nhất
                                    if ($dRow[1] > $maxColumnIndex) {
                                        $maxColumnIndex = $dRow[1];
                                    }
                                    $maxRowIndex = ($dRow[0] + $cIndex);
                                }
                            }
                        }

                        $newRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 1 + $cIndex;

                        for ($cellIndex = 0; $cellIndex <= $maxColumnIndex; $cellIndex++) {
                            $column = Coordinate::stringFromColumnIndex($cellIndex + 1);

                            $originRowIndex = $this->templateVariable['$LOOP_START'][0][0] + 1;

                            $orginStyle = $sheet->getDelegate()->getStyle($column . $originRowIndex);

                            $range = $column . $newRowIndex . ":" . $column . $newRowIndex;

                            $sheet->getDelegate()->duplicateStyle($orginStyle, $range);

                            $h = $sheet->getRowDimension($this->templateVariable['$LOOP_START'][0][0] + 1)->getRowHeight();

                            $sheet->getRowDimension($newRowIndex)->setRowHeight($h);
                        }
                    }
                }
            },
        ];
    }
}
