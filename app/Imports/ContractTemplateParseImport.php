<?php

namespace App\Imports;

use ErrorException;
use Exception;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Storage;
use App\Exports\CalculationSheetSalaryExportFromTemplate;
use Illuminate\Http\File;
use App\Support\Constant;

class ContractTemplateParseImport implements ToCollection, WithStartRow
{
    public $data;
    public function collection(Collection $rows)
    {
        $error = false;
        $errorLabel = array();

        $filteredData = collect([]);
        
        foreach ($rows as $key => $row) {
            $allColsIsEmpty = empty(array_filter($row->toArray(), function($v) {
                return !empty($v);
            }));
            if (!$allColsIsEmpty) {
                $filteredData->push($row);
            }
        }
        
        $filteredData = $filteredData->map(function($data, $key) {

            $checkDate = function($fieldName) use ( &$data, $key) {
                if (isset($data[$fieldName]) && !empty($data[$fieldName])) {

                    $value = $this->transformDate($data[$fieldName]);

                    $value = (explode(' ', $value))[0];

                    if ($value) {
                        $data[$fieldName] = $value;
                    } else {
                        $field = $key.'.'.$fieldName;
                    }
                } else {
                    unset($data[$fieldName]);
                }
            };

            $checkDate('probation_start_date');
            $checkDate('probation_end_date');
            $checkDate('official_contract_signing_date');
            $checkDate('date_of_birth');
            $checkDate('effective_date_of_social_insurance');
            $checkDate('is_card_issue_date');
            return $data;
        });

        $this->data = $filteredData;

        return $this->data;
    }

    /**
     * @return int
     */
     public function startRow()
     : int
     {
         return 1;
     }

     public function transformDate($value, $format = 'Y-m-d')
    {
        if ((is_int((int)$value)) && (strlen((string)$value) == 4)) {
            $value = (string)$value . '-' . Constant::DEFAULT_APPEND_TO_DATE;
            return Carbon::createFromFormat($format, $value);
        }

        try {

            return Carbon::instance(Date::excelToDateTimeObject($value));
        } catch (ErrorException $e) {
            return null;
        }
    }
}