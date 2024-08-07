<?php

namespace App\Exports;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\PaidLeaveBalance;
use App\Models\PaidLeaveChange;
use App\Models\Client;
use Carbon\Carbon;

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


class PaidLeaveBalanceExport implements WithEvents, ShouldAutoSize
{

    use Exportable;

    protected $client;
    protected $year;
    protected $totalList = 0;
    protected $dataIndex = 7;

    public function __construct(Client $client, Int $year)
    {
        $this->client = $client;
        $this->year = $year;

        return $this;
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function (BeforeExport $event) {

                $path = storage_path('app/ClientEmployeeExportTemplate/paid_leave_balance.xlsx');

                $event->writer->reopen( new \Maatwebsite\Excel\Files\LocalTemporaryFile($path), Excel::XLSX);
    
                $event->writer->getSheetByIndex(0);

                $beforeYear = $this->year - 1;
                $thisYear = $this->year;
                $thisMonthDate  = strtoupper(Carbon::now()->format('F Y'));

                $sheet = $event->getWriter()->getSheetByIndex(0);

                $sheet = $this->setValue($this->client->company_name, 1, 3, $sheet);
                if ($this->year == date('Y')) {
                    $sheet = $this->setValue("SUMMARY REMAIN PAID LEAVES UNTIL $thisMonthDate OF EMPLOYEES", 3, 3, $sheet);
                } else {
                    $sheet = $this->setValue("SUMMARY REMAIN PAID LEAVES IN $thisYear OF EMPLOYEES", 3, 3, $sheet);
                }

                $sheet = $this->setValue("Balance in $beforeYear", 6, 5, $sheet);
                $sheet = $this->setValue("Beginning $thisYear (hours)", 6, 6, $sheet);
                $sheet = $this->setValue("Number of  leave hours used in $thisYear", 6, 7, $sheet);
                $sheet = $this->setValue("Total paid leave in $thisYear", 6, 20, $sheet);

                for($i = 1; $i < 13; $i++) {
                    $sheet = $this->setValue(Carbon::parse('1-' . $i . '-' . $thisYear)->format('d/m/Y'), 6, ($i + 7), $sheet);
                }
                
                $paidLeaveBalances = PaidLeaveBalance::select('paid_leave_balances.*')
                ->where('paid_leave_balances.client_id', $this->client->id)
                ->where('paid_leave_balances.year', $this->year)
                ->join('client_employees', 'paid_leave_balances.client_employee_id', '=', 'client_employees.id')
                ->where('client_employees.status', 'đang làm việc')
                ->with('clientEmployee')->orderBy('paid_leave_balances.month')->get();

                $beforePaidLeaveBalances = PaidLeaveBalance::select('paid_leave_balances.*')
                ->where('paid_leave_balances.client_id', $this->client->id)
                ->where('paid_leave_balances.year', $this->year - 1)
                ->join('client_employees', 'paid_leave_balances.client_employee_id', '=', 'client_employees.id')
                ->where('client_employees.status', 'đang làm việc')
                ->with('clientEmployee')->orderBy('paid_leave_balances.month')->get();

                $groupedPaidLeaveBalances = $paidLeaveBalances->groupBy('client_employee_id')->all();
                $groupedBeforePaidLeaveBalances = $beforePaidLeaveBalances->groupBy('client_employee_id')->all();

                logger('groupedPaidLeaveBalances ' . $this->client->id, [$groupedPaidLeaveBalances]);
                logger('groupedBeforePaidLeaveBalances ' . $this->client->id, [$groupedBeforePaidLeaveBalances]);

                $index = 1;

                foreach($groupedPaidLeaveBalances as $g) {

                    $gf = $g->first();
                    
                    $employee = $gf->clientEmployee;
                    $oldestContract = $employee->oldestContracts->first();
                    $row = $index + 6;
                    
                    $balanceIn = $this->getBalanceIn($employee->id, $groupedBeforePaidLeaveBalances);
                    $beginningBalance = 0;
                    $totalPaidLeave = $g->sum('used_balance');
                    $remainPaidLeave = $g->last()->end_balance;
                    $startWorkingDate = $oldestContract ? Carbon::parse($oldestContract->contract_signing_date)->format('d/m/Y') : '';
                    
                    $sheet = $this->setValue($index, $row, 1, $sheet);
                    $sheet = $this->setValue($employee->code, $row, 2, $sheet);
                    $sheet = $this->setValue($employee->full_name, $row, 3, $sheet);

                    $sheet = $this->setValue($startWorkingDate, $row, 4, $sheet);
                    $sheet = $this->setValue($balanceIn, $row, 5, $sheet);

                    $sheet = $this->setValue($totalPaidLeave, $row, 20, $sheet);
                    $sheet = $this->setValue($remainPaidLeave, $row, 21, $sheet);

                    foreach($g as $i => $p) 
                    {
                        $sheet = $this->setValue($p->used_balance, $row, ($p->month + 7), $sheet);
                        $beginningBalance += $p->added_balance;
                    }

                    $sheet = $this->setValue($beginningBalance, $row, 6, $sheet);
                    $sheet = $this->setValue(($balanceIn + $beginningBalance), $row, 7, $sheet);

                    $index++;
                }

                $this->totalList = count($groupedPaidLeaveBalances);

                $sheet = $this->setValue('TOTAL', $this->totalList + $this->dataIndex, 3, $sheet);

                for($i = 5; $i < 22; $i++){
                    $pos1 = Coordinate::stringFromColumnIndex($i) . 7;
                    $pos2 = Coordinate::stringFromColumnIndex($i) . ($this->totalList + $this->dataIndex - 1);

                    $sheet = $this->setValue('=SUM(' . $pos1 . ':' . $pos2 . ')', $this->totalList + $this->dataIndex, $i, $sheet);
                }

                $sheet = $this->styleSheet($sheet);
            },
        ];
    }

    public function styleSheet($sheet)
    {

        $col = Coordinate::stringFromColumnIndex(21);

        $cellRange = 'A' . $this->dataIndex . ':' . $col . ($this->totalList + $this->dataIndex);

        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ]
        ];

        $sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);

        $sheet->getDelegate()->getStyle('C' . ($this->totalList + $this->dataIndex))->applyFromArray([
            'font' => array(
                'bold' => true
            ),
        ]);

        $sheet->getDelegate()->getStyle('C' . ($this->totalList + $this->dataIndex))->getAlignment()->applyFromArray([
            'vertical'   => 'center',
            'horizontal' => 'center'
        ]);

        return $sheet;
    }

    protected function getBalanceIn($clientEmployeeId, $groupedBeforePaidLeaveBalances)
    {
        if(isset($groupedBeforePaidLeaveBalances[$clientEmployeeId]))
        {
            $employeeBeforePaidLeaveBalances = $groupedBeforePaidLeaveBalances[$clientEmployeeId]->firstWhere('month', 12);

            if($employeeBeforePaidLeaveBalances){
                return $employeeBeforePaidLeaveBalances['end_balance'];
            }
        }

        return 0;
    }

    public function setValue($value, $row, $col, $sheet)
    {
        $colIndex = Coordinate::stringFromColumnIndex($col);

        $pos = $colIndex . $row;

        $sheet->setCellValue($pos, $value);

        return $sheet;
    }
}
