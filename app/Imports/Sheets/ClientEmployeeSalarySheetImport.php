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
use App\Models\ClientEmployeeContract;
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

class ClientEmployeeSalarySheetImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
        "status" => ['number', 'required'],
        "type_of_employment_contract" => ['number', 'required'],
        "contract_no_1" => ['string'],
        "probation_start_date" => ['date'],
        "probation_end_date" => ['date'],
        "contract_no_2" => ['string'],
        "definite_term_contract_first_time_start_date" => ['date'],
        "definite_term_contract_first_time_end_date" => ['date'],
        "contract_no_3" => ['string'],
        "definite_term_contract_second_time_start_date" => ['date'],
        "definite_term_contract_second_time_end_date" => ['date'],
        "contract_no_4" => ['string'],
        "indefinite_term_contract_start_date" => ['date'],
        "contract_no_5" => ['string'],
        "other_term_contract_start_date" => ['date'],
        "resignation_date" => ['date']
    ];

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    public function collection(Collection $rows)
    {
        logger("ClientEmployeeImport::collection BEGIN");

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

        DB::beginTransaction();

        $errors = [];
        $dateErrors = new MessageBag();

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

            $transformStatus = function ($value) {
                $value = (int)($value);
                $statuses = [
                    "1" => "đang làm việc",
                    "2" => "nghỉ không lương",
                    "3" => "nghỉ thai sản",
                    "4" => "nghỉ việc",
                ];
                return isset($statuses[$value]) ? $statuses[$value] : $statuses[1];
            };

            foreach ($row as $k => $v) {
                if (str_ends_with($k, '_date')) {
                    $checkDate($k);
                }
            }

            $data = [];

            $clientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                ->where('code', '=', $row['code'])
                ->first();

            if (!empty($clientEmployee)) {
                $data['status'] = $transformStatus($row['status']);
                $data['type_of_employment_contract'] = $this->transformContractType($row['type_of_employment_contract']);

                $clientEmployee->update($data);

                $this->updateContract($clientEmployee->id, $row);
            } else {
                $errors[] = $row['code'];
            }
        }

        if ($errors) {
            DB::rollBack();

            $mesg = json_encode(['type' => 'validate', 'msg' => 'Salary Information: Can not find these employees: ' . implode(', ', $errors)]);

            throw new CustomException(
                $mesg,
                'VALIDATION_RULES'
            );
        }

        DB::commit();
    }

    public function transformContractType($value)
    {
        switch ($value) {
            case 1:
                return "khongthoihan";
                break;
            case 2:
                return "chinhthuc";
                break;
            case 3:
                return "thoivu";
                break;
            case 4:
                return "thuviec";
                break;
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

    public function updateContract($client_employee_id, $row)
    {
        if (isset($row['contract_no_1']) && !is_null($row['contract_no_1'])) {

            $data = ['contract_code' => $row['contract_no_1'], 'contract_type' => 'thuviec'];

            if (isset($row['probation_start_date']) && $row['probation_start_date']) {
                $data['contract_signing_date'] = $row['probation_start_date'];
            }

            if (isset($row['probation_end_date']) && $row['probation_end_date']) {
                $data['contract_end_date'] = $row['probation_end_date'];
            }

            ClientEmployeeContract::updateOrCreate(
                ['client_employee_id' => $client_employee_id, 'contract_code' => $row['contract_no_1'], 'contract_type' => 'thuviec'],
                $data
            );
        }

        if (isset($row['contract_no_2']) && !is_null($row['contract_no_2'])) {

            $data = ['contract_code' => $row['contract_no_2'], 'contract_type' => 'co-thoi-han-lan-1'];

            if (isset($row['definite_term_contract_first_time_start_date']) && $row['definite_term_contract_first_time_start_date']) {
                $data['contract_signing_date'] = $row['definite_term_contract_first_time_start_date'];
            }

            if (isset($row['definite_term_contract_first_time_end_date']) && $row['definite_term_contract_first_time_end_date']) {
                $data['contract_end_date'] = $row['definite_term_contract_first_time_end_date'];
            }

            ClientEmployeeContract::updateOrCreate(
                ['client_employee_id' => $client_employee_id, 'contract_code' => $row['contract_no_2'], 'contract_type' => 'co-thoi-han-lan-1'],
                $data
            );
        }

        if (isset($row['contract_no_3']) && !is_null($row['contract_no_3'])) {

            $data = ['contract_code' => $row['contract_no_3'], 'contract_type' => 'co-thoi-han-lan-2'];

            if (isset($row['definite_term_contract_second_time_start_date']) && $row['definite_term_contract_second_time_start_date']) {
                $data['contract_signing_date'] = $row['definite_term_contract_second_time_start_date'];
            }

            if (isset($row['definite_term_contract_second_time_end_date']) && $row['definite_term_contract_second_time_end_date']) {
                $data['contract_end_date'] = $row['definite_term_contract_second_time_end_date'];
            }

            ClientEmployeeContract::updateOrCreate(
                ['client_employee_id' => $client_employee_id, 'contract_code' => $row['contract_no_3'], 'contract_type' => 'co-thoi-han-lan-2'],
                $data
            );
        }

        if (isset($row['contract_no_4']) && !is_null($row['contract_no_4'])) {

            $data = ['contract_code' => $row['contract_no_4'], 'contract_type' => 'khong-xac-dinh-thoi-han'];

            if (isset($row['indefinite_term_contract_start_date']) && $row['indefinite_term_contract_start_date']) {
                $data['contract_signing_date'] = $row['indefinite_term_contract_start_date'];
            }

            ClientEmployeeContract::updateOrCreate(
                ['client_employee_id' => $client_employee_id, 'contract_code' => $row['contract_no_4'], 'contract_type' => 'khong-xac-dinh-thoi-han'],
                $data
            );
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
        return 20;
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->client_id;
    }

    public function validate($sheet)
    {

        $RIGHT_HEADER = $this->getRightHeader();

        $data = $this->getData($sheet);

        if (!$data) return [];

        $errors = [];

        $header = array_values($data['header']);

        $filteredData = $data['rows'];

        $HEADER = array_keys($RIGHT_HEADER);

        $diffCols = array_diff($HEADER, $header);

        $missingCols = [];
        $errorFormats = [];

        foreach ($diffCols as $c) {
            if (in_array($c, $HEADER)) {
                $missingCols[] = $c;
            }
        }

        if ($missingCols) {
            $errors['missing_cols'] = $missingCols;
        }


        foreach ($filteredData as $index => $d) {
            $filteredData[$index] = array_combine($header, $d);
        }

        $newEmployeeCount = 0;
        foreach ($filteredData as $index => $row) {
            $colIndex = 1;
            $rowIndex = $index;

            $clientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                ->where('code', '=', $row['code'])
                ->first();

            //don't allow to create new employee if limit setting is over
            if (empty($clientEmployee) && ($row['status'] != 4)) {
                $newEmployeeCount++;
            }
            foreach ($row as $col => $value) {
                $required = false;

                if (isset($RIGHT_HEADER[$col])) {
                    $required = isset($RIGHT_HEADER[$col][1]) && ($RIGHT_HEADER[$col][1] == 'required');
                }

                if ($required && is_null($value)) {
                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'required'];
                } elseif (isset($RIGHT_HEADER[$col])) {
                    switch ($RIGHT_HEADER[$col][0]) {
                        case 'number':
                            if (!is_numeric($value)) {
                                $errorFormats[] = [
                                    'row' => $rowIndex, 'col' => $colIndex, 'name' => $col,
                                    'error' => 'not valid format number',
                                ];
                            }
                            break;
                        case 'date':

                            if ($value && !$this->isDate($value)) {
                                $errorFormats[] = [
                                    'row' => $rowIndex, 'col' => $colIndex, 'name' => $col,
                                    'error' => 'not valid format date',
                                ];
                            }
                            break;
                        case 'code_employee_exists':
                            if (!$this->hasEmployee($value))
                                $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'This employee code is not exists'];
                            break;
                        case 'code_employee_not_exists':
                            if ($this->hasEmployee($value))
                                $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'This employee code is exists'];
                            break;
                        case 'username_exists':
                            if (!$this->checkUsername($row))
                                $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this username is existed'];
                            break;
                        case 'country_exists':
                            if (!in_array($row['nationality'], Constant::COUNTRY_LIST))
                                $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this nationality is not existed'];
                            break;
                        default:
                            break;
                    }
                }

                $colIndex++;
            }
        }
        if ($newEmployeeCount && !ClientHelper::validateLimitActivatedEmployeeWithNewEmployeeNumber($this->client_id, $newEmployeeCount)) {
            throw new CustomException(__('error.exceeded_employee_limit'), 'ValidationException');
        }

        if ($errorFormats)
            $errors['formats'] = $errorFormats;

        if ($errors) {
            $errors['startRow'] = $this->startRow();
        }

        return $errors;
    }
}
