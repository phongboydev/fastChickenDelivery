<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use Maatwebsite\Excel\Concerns\FromCollection;

class CalculationSheetSalaryExportCsv implements FromCollection
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

    public function collection()
    {

        $calculationSheetId = $this->calculationSheetId;

        $calculationSheetClientEmployeeData = [];

        $calculationSheetClientEmployeeLists = CalculationSheetClientEmployee::select('*')
            ->with('calculationSheet')
            ->with('clientEmployee')
            ->join('client_employees', 'calculation_sheet_client_employees.client_employee_id', '=', 'client_employees.id')
            ->where('calculation_sheet_id', '=', $calculationSheetId)
            ->orderBy('client_employees.code', 'ASC')
            ->get();

        $totalCalculatedValue = 0;

        if (!empty($calculationSheetClientEmployeeLists)) {

            foreach ($calculationSheetClientEmployeeLists as $cIndex => $item) {

                $employee = $item->clientEmployee->toArray();

                $calculationSheetClientEmployeeDataTmp = [
                    'NO' => $cIndex + 1,
                    'CODE' => $item['clientEmployee']->code,
                    'NAME' => $item['clientEmployee']->full_name
                ];

                $calculationSheetVariables = CalculationSheetVariable::select('*')
                    ->where('calculation_sheet_id', '=', $item['calculationSheet']->id)
                    ->where('client_employee_id', '=', $item['clientEmployee']->id)
                    ->get();

                if (!empty($calculationSheetVariables)) {

                    $calVariableNames = $calculationSheetVariables->pluck('variable_name')->toArray();

                    foreach ($this->variables as $variable_name) {

                        if (in_array($variable_name, $calVariableNames)) {
                            $variable = $calculationSheetVariables->firstWhere('variable_name', $variable_name);
                            $calculationSheetClientEmployeeDataTmp[$variable_name] = $variable['variable_value'];
                        } else {
                            if (isset($employee[$variable_name])) {
                                $variableValue = $employee[$variable_name];
                                $calculationSheetClientEmployeeDataTmp[$variable_name] = $variableValue;
                            }
                        }
                    }
                }

                $calculationSheetClientEmployeeDataTmp['calculated_value'] = $item['calculated_value'];

                $calculationSheetClientEmployeeData[] = $calculationSheetClientEmployeeDataTmp;

                $totalCalculatedValue += $item['calculated_value'];
            }
        }

        $values = [];

        if (!empty($calculationSheetClientEmployeeData)) {
            foreach ($calculationSheetClientEmployeeData as $key => $value) {
                $tmp = [];
                foreach ($this->variables as $variable) {
                    if (isset($value[$variable])) {
                        $tmp[] = $value[$variable];
                    }
                }

                if ($tmp)
                    $values[] = $tmp;
            }
        }

        $results = array_merge([$this->variables], $values);

        return collect($results);
    }
}
