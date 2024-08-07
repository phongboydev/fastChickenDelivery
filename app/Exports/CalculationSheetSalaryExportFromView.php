<?php

namespace App\Exports;

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


class CalculationSheetSalaryExportFromView implements FromView, WithTitle, WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $calculationSheetId;
    protected $variables;

    private $total_list = 0;
    private $total_variables = 0;

    public function __construct(string $calculationSheetId, array $variables)
    {
        $this->calculationSheetId = $calculationSheetId;
        $this->variables = $variables;

        return $this;
    }

    public function view()
    : View
    {
        $calculationSheetId = $this->calculationSheetId;

        $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::select('*')
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->join('client_employees', 'calculation_sheet_client_employees.client_employee_id', '=', 'client_employees.id')
            ->where('calculation_sheet_id', '=', $calculationSheetId)
            ->orderBy('client_employees.code', 'ASC')
            ->get();

        $variableLabels = array();

        $totalCalculatedValue = 0;

        if (!empty($calculationSheetClientEmployeeLists)) {
            foreach ($calculationSheetClientEmployeeLists as $item) {
                $calculationSheetVariables = CalculationSheetVariable::select('*')
                    ->where('calculation_sheet_id', '=', $item['calculationSheet']['id'])
                    ->where('client_employee_id', '=', $item['clientEmployee']['id'])
                    ->whereIn('variable_name', $this->variables)
                    ->get();

                $variables = array();

                if (!empty($this->variables)) {
                    foreach ($this->variables as $index => $variable) {
                        if (!empty($calculationSheetVariables)) {
                            foreach ($calculationSheetVariables as $calculationSheetVariable) {
                                if ($calculationSheetVariable['variable_name'] == $variable) {
                                    $variables[$variable] = is_numeric($calculationSheetVariable['variable_value']) ? round($calculationSheetVariable['variable_value'], 2) : $calculationSheetVariable['variable_value'];
                                    $variableLabels[$variable] = '';
                                    break;
                                }
                            }
                        }
                    }
                }

                $totalCalculatedValue += $item['calculated_value'];

                $item['calculated_value'] = number_format($item['calculated_value'], 2);
                $item['calculationSheetVariable'] = $calculationSheetVariables;
                $item['variables'] = $variables; 
            }
        }

        if (!empty($calculationSheetClientEmployeeLists)) {
            $this->total_list = count($calculationSheetClientEmployeeLists);
        }
        if (!empty($this->variables)) {
            $this->total_variables = count($this->variables);
        }

        $totalRow = [];
        
        foreach(array_values($variableLabels) as $index => $v) {
            if($index > 1){
                $totalRow[] = 0;
            }else{
                $totalRow[] = '';
            } 
        }

        $totalRow[] = number_format($totalCalculatedValue, 2);

        if( !empty($calculationSheetClientEmployeeLists) ) {
            $j = 2;

            foreach( $calculationSheetClientEmployeeLists[0]['variables'] as $index => $variable ) {
                foreach ($calculationSheetClientEmployeeLists as $item) {

                    if(is_numeric($item['variables'][$index])){
                        $totalRow[$j] += $item['variables'][$index];
                    }else{
                        $totalRow[$j] = '';
                    }
                }

                $j++;
            }
        }
        
        return view('exports.calculation-sheet-client-employee-salary', [
            'variableLabels'                      => $variableLabels,
            'variables'                           => $this->variables,
            'calculationSheetClientEmployeeLists' => $calculationSheetClientEmployeeLists,
            'countVariable'                       => $this->total_variables,
            'totalRow'                            => $totalRow

        ]);
    }

    public function calTotalColumn( $columns ) {

    }

    public function title()
    : string
    {
        return 'Bang luong';
    }

    public function registerEvents()
    : array
    {

        return [
            AfterSheet::class => function (AfterSheet $event) {

                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                    'font'    => array(
                        'name' => 'Arial',
                        'size' => 11,
                    )
                ];


                $event->sheet->getDelegate()->getStyle('A1')->applyFromArray([
                    'font' => array(
                        'name' => 'Arial',
                        'size' => 13,
                    ),
                ]);

                $col = Coordinate::stringFromColumnIndex($this->total_variables + 1);
                $event->sheet->getDelegate()->getStyle('A3:' . $col . '3')->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'center',
                ]);

                $event->sheet->getDelegate()->getStyle('A4:A' . (4 + $this->total_list))->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]);

                $cellRange = 'A3:' . $col . (3 + $this->total_list + 1);

                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'right'
                ])->setWrapText(true);
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);

                $event->sheet->getDelegate()->getStyle('A3:' . $col . '3')->applyFromArray([
                    'font' => array(
                        'name' => 'Arial',
                        'bold' => true
                    )
                ]);
            },
        ];
    }
}
