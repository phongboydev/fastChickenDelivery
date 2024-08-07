<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Exports\CalculationSheetSalaryExportFromTemplate;
use Illuminate\Http\File;

class CalculationSheetSalaryImport implements ToCollection
{
    protected $calculationSheetId;
    protected $variables;
    protected $templateExport;
    protected $pathFile;
    protected $employeeGroupIds;

    public function __construct(string $calculationSheetId, array $variables, string $templateExport, string $pathFile, array $employeeGroupIds = [])
    {
        $this->calculationSheetId = $calculationSheetId;
        $this->variables = $variables;
        $this->templateExport = $templateExport;
        $this->pathFile = $pathFile;
        $this->employeeGroupIds = $employeeGroupIds;
    }

    public function collection(Collection $rows)
    {
        $templateVariable = [
            '$COMPANY_NAME' => [],
            '$ADDRESS' => [],
            '$DATE_FROM' => [],
            '$DATE_TO' => [],
            '$LOOP_START' => [],
            '$NO' => [],
            '$CODE' => [],
            '$NAME' => [],
            '$SALARY' => [],
            '$WORKPLACE' => [],
            '$ONBOARD_DATE' => [],
            '$MST_CODE' => [],
            '$LOOP_END' => []
        ];

        $calculationSheetClientEmployee = CalculationSheetClientEmployee::select('*')
            ->where('calculation_sheet_id', '=', $this->calculationSheetId)
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
            ->first();

        if (!empty($calculationSheetClientEmployee)) {
            $calculationSheetVariables = CalculationSheetVariable::select('*')
                ->where('calculation_sheet_id', '=', $this->calculationSheetId)
                ->where('client_employee_id', '=', $calculationSheetClientEmployee->client_employee_id)
                ->get();

            if (!empty($calculationSheetVariables)) {
                foreach ($calculationSheetVariables as $v) {
                    $templateVariable['$' . strtoupper($v['variable_name'])] = [];
                }

                $templateVariable['$LOOP_END'] = [];
            }
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $key => $value) {
                foreach ($templateVariable as $d => $v) {
                    if ($value === $d) {
                        $templateVariable[$d][] = [$rowIndex, $key];
                    }
                }
            }
        }

        Excel::store((new CalculationSheetSalaryExportFromTemplate(
            $this->calculationSheetId,
            $this->variables,
            $this->templateExport,
            $templateVariable,
            $rows,
            $this->employeeGroupIds
        )), $this->pathFile, 'minio');
    }
}
