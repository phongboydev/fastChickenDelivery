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
use App\Exports\PayrollAccountantExportFromTemplate;
use Illuminate\Http\File;

/**
 * Used to read placeholder variables from excel file
 * Class PayrollAccountantImport
 * @package App\Imports
 */
class PayrollAccountantImport implements ToCollection
{

    protected $calculationSheetId;
    protected $variables;
    protected $translates;
    protected $templateExport;
    protected $pathFile;

    public function __construct(string $calculationSheetId, array $variables, array $translates, string $templateExport, string $pathFile)
    {
        $this->calculationSheetId = $calculationSheetId;
        $this->variables = $variables;
        $this->translates = $translates;
        $this->templateExport = $templateExport; // input
        $this->pathFile = $pathFile; // output
        return $this;
    }

    public function collection(Collection $rows)
    {
        $templateVariable = $this->getTemplateVariables($rows);

        Excel::store((new PayrollAccountantExportFromTemplate(
            $this->calculationSheetId,
            $this->variables,
            $this->translates,
            $this->templateExport,
            $templateVariable
        )), $this->pathFile, 'minio');
    }

    /**
     * TODO maybe we can parse this in PayrollAccountantExportFromTemplate
     * @param $rows
     * @return array
     */
    protected function getTemplateVariables($rows)
    {
        $templateVariable = [];
        if (!empty($this->variables)) {
            foreach ($this->variables as $variable) {
                $templateVariable['$' . $variable['variable_name']] = [];
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

        return $templateVariable;
    }
}
