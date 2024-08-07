<?php

namespace App\Imports\Sheets;

use App\Support\ClientHelper;
use ErrorException;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\ClientEmployeeDependent;
use App\User;
use App\Models\Client;
use App\Models\ClientEmployee;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;

class ClientEmployeeDependentBasicSheetImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
        "name_dependents" => ['string', 'required'],
        "tax_code" => ['string', 'required'],
        "relationship_code" => ['in:01,02', 'required'],
        "from_date" => ['date', 'required'],
        "to_date" => ['date']
    ];

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    public function collection(Collection $rows)
    {

        $filteredData = collect([]);

        foreach ($rows as $key => $row) {
            $allColsIsEmpty = empty(array_filter($row->toArray(), function ($v) {
                return !empty($v);
            }));
            if (!$allColsIsEmpty) {

                $r = array_filter($row->toArray(), function ($v, $k) {
                    return $k;
                }, ARRAY_FILTER_USE_BOTH);

                $filteredData->push($r);
            }
        }

        if ($filteredData) {
            DB::beginTransaction();
            $errors = [];
            $dateErrors = new MessageBag();
            // loop list dependent information
            foreach ($filteredData as $row) {
                $checkDate = function ($fieldName) use ($dateErrors, &$row, $key) {
                    if (isset($row[$fieldName]) && !empty($row[$fieldName])) {

                        $value = $this->transformDate($row[$fieldName]);
                        $value = (explode(' ', $value))[0];

                        if ($value) {
                            $row[$fieldName] = $value;
                        } else {
                            $field = $key . '.' . $fieldName;
                            $dateErrors->add($field, trans('validation.date', ['attribute' => $field]));
                        }
                    } else {
                        unset($row[$fieldName]);
                    }
                };
                foreach ($row as $k => $v) {
                    if (str_ends_with($k, '_date')) {
                        $checkDate($k);
                    }
                }

                $clientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                    ->where('code', '=', $row['code'])
                    ->first();

                if (!empty($clientEmployee)) {
                    $this->storeEmployeeDependent($clientEmployee->id, $row);
                } else {
                    $errors[] = $row['code'];
                }
            }

            if ($errors) {
                DB::rollBack();

                $mesg = json_encode(['type' => 'validate', 'msg' => 'Dependent Information: Can not find these employees: ' . implode(', ', $errors)]);

                throw new CustomException(
                    $mesg,
                    'VALIDATION_RULES'
                );
            }

            DB::commit();
        }
    }


    public function transformDate($value, $format = 'Y-m-d')
    {
        if ((is_int((int)$value)) && (strlen((string)$value) == 5)) {

            $value = Date::excelToDateTimeObject($value);

            return $value->format($format);
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (Exception $e1) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            } catch (Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Store info dependent of staff
     */

    private function storeEmployeeDependent($client_employee_id, $infoDependent)
    {
        // check client_employee_id & infoDependent not empty
        if ($client_employee_id && !empty($infoDependent)) {
            ClientEmployeeDependent::updateOrCreate(['client_employee_id' => $client_employee_id, 'tax_code' => $infoDependent['tax_code']], $infoDependent);
        }
    }


    public function isDynamicsStartRow()
    {
        return false;
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 4;
    }

    public function endRow($rows): int
    {
        return -1;
    }

    public function startHeader()
    {
        return 1;
    }

    public function totalCol()
    {
        return 21;
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->client_id;
    }
}
