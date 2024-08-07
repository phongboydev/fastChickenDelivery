<?php

namespace App\Imports;

use App\Exceptions\CustomException;
use App\Imports\Sheets\ClientEmployeeBasicSheetImport;
use App\Imports\Sheets\ClientEmployeeSalarySheetImport;
use App\Imports\Sheets\ClientEmployeeDependentBasicSheetImport;
use App\Models\Client;
use App\Models\ClientDepartment;
use App\Models\ClientPosition;
use App\Models\ClientEmployee;
use App\Support\Constant;
use App\User;
use Carbon\Carbon;
use ErrorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ClientEmployeeImportMultiSheet implements ToCollection, WithHeadingRow, WithStartRow, WithMultipleSheets
{

    use Importable;

    protected $client_id = null;
    protected $totalSheet = 0;
    protected $withoutEvent = false;
    protected $sheetNames = [];
    protected $creatorId = 0;
    function __construct($clientId, $totalSheet, $withoutEvent = false, $sheetNames, $creatorId = 0)
    {
        $this->client_id = $clientId;
        $this->totalSheet = $totalSheet;
        $this->withoutEvent = $withoutEvent;
        $this->sheetNames = $sheetNames;
        $this->creatorId = $creatorId;
    }

    public function sheets(): array
    {
        return [
            $this->sheetNames[0] => new ClientEmployeeBasicSheetImport($this->client_id, $this->withoutEvent, $this->creatorId),
            $this->sheetNames[1] => new ClientEmployeeSalarySheetImport($this->client_id),
            $this->sheetNames[2] => new ClientEmployeeDependentBasicSheetImport($this->client_id),
        ];
    }

    public function collection(Collection $rows)
    {
        logger("ClientEmployeeImport::collection BEGIN");
        $error = false;
        $errorLabel = array();
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

        // Date validation
        $dateErrors = new MessageBag();
        $filteredData = $filteredData->map(function ($data, $key) use ($dateErrors) {

            $checkDate = function ($fieldName) use ($dateErrors, &$data, $key) {
                if (isset($data[$fieldName]) && !empty($data[$fieldName])) {

                    $value = $this->transformDate($data[$fieldName]);

                    $value = (explode(' ', $value))[0];

                    if ($value) {
                        $data[$fieldName] = $value;
                    } else {
                        $field = $key . '.' . $fieldName;
                        $dateErrors->add($field, trans('validation.date', ['attribute' => $field]));
                    }
                } else {
                    unset($data[$fieldName]);
                }
            };

            $checkDate('date_of_birth');
            $checkDate('effective_date_of_social_insurance');

            foreach ($data as $k => $v) {
                if (str_ends_with($k, '_date')) {
                    $checkDate($k);
                }
            }

            return $data;
        });

        if (!$dateErrors->isEmpty()) {
            logger("ClientEmployeeImport::collection date validation error", [$dateErrors]);
            throw new CustomException(
                $dateErrors,
                'ValidationException'
            );
        }


        DB::beginTransaction();
        $i = 4;
        if (!empty($this->client_id)) {
            $clientOj = Client::where('id', $this->client_id)->first();

            if ($clientOj) {

                foreach ($filteredData as $row) {

                    $existClientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                        ->where('code', '=', $row['code'])
                        ->first();

                    $overwrite = $row['overwrite'] ?? false;

                    if (!$existClientEmployee || $overwrite) {

                        $data = $row;

                        if ($existClientEmployee) {
                            $clientEmployee = $existClientEmployee;
                        } else {
                            $clientEmployee = new ClientEmployee();

                            // prepare user import file data
                            foreach ($data as $key => $value) {
                                $intData = array("is_tax_applicable", "is_insurance_applicable", "number_of_dependents");

                                foreach ($intData as $d) {
                                    if (!isset($data[$d])) {
                                        $data[$d] = 0;
                                    }
                                }
                            }

                            foreach ($data as $key => $value) {
                                $intData = array(
                                    'salary', 'allowance_for_responsibilities', 'currency', 'fixed_allowance', 'salary_for_social_insurance_payment', 'social_insurance_number', 'medical_care_hospital_name', 'medical_care_hospital_code', 'nationality', 'nation', 'bank_account', 'bank_account_number', 'bank_name', 'bank_branch', 'contact_phone_number',
                                );

                                foreach ($intData as $d) {
                                    if (!isset($data[$d])) {
                                        $data[$d] = "";
                                    }
                                }
                            }
                        }

                        $userId = null;
                        if (isset($data['allow_login']) && $data['allow_login'] == 1) {
                            $hasLogin = !empty(trim($clientEmployee->user_id));

                            if ($hasLogin) {
                                $userId = trim($clientEmployee->user_id);
                            } elseif (!empty($data['username'])) {
                                $userData['username'] = trim(strtolower($data['username']));
                                $userData['password'] = bcrypt("000000"); // will be generated later
                                $userData['name'] = $data['full_name'];
                                $userData['email'] = trim($data['email']);
                                $userData['is_internal'] = 0;
                                $userData['client_id'] = $this->client_id;

                                $user = User::where('client_id', $this->client_id)
                                    ->where('username', $this->client_id . '_' . $userData['username'])
                                    ->first();
                                if (!$user) {
                                    $validatorUserData = Validator::make($userData, [
                                        'username' => ['required',
                                        'regex:/^[a-z0-9_.]+$/',
                                        'max:255']
                                    ]);

                                    if ($validatorUserData->fails()) {
                                        $errorsMsg = $validatorUserData->errors();
                                        throw new CustomException(
                                            $errorsMsg . ' ' . $userData['username'],
                                            'ValidationExceptionUserData'
                                        );
                                    }

                                    $userModel = new User($userData);
                                    $userModel->save();
                                    $userId = $userModel->id;
                                } else {
                                    $error = true;
                                    $errorLabel[] = "Dòng " . $i . " - Tài khoản nhân viên đã tồn tại: " . $userData['username'];
                                }
                            }
                        }

                        $transformStatus = function ($value) {
                            $statuses = [
                                "1" => "đang làm việc",
                                "2" => "nghỉ không lương",
                                "3" => "nghỉ thai sản",
                                "4" => "nghỉ việc",
                            ];
                            return isset($statuses[$value]) ? $statuses[$value] : $statuses[1];
                        };

                        $transformRole = function ($value) {
                            $statuses = [
                                "1" => "staff",
                                "2" => "leader",
                                "3" => "accountant",
                                "4" => "hr",
                                "5" => "manager"
                            ];
                            return isset($statuses[$value]) ? $statuses[$value] : $statuses[1];
                        };

                        $data['client_id'] = $this->client_id;

                        if (isset($data['household_head_date_of_birth']))
                            $data['household_head_date_of_birth'] = !empty($row['household_head_date_of_birth']) ? $this->transformDate($row['household_head_date_of_birth']) : null;
                        if ($userId) {
                            $data['user_id'] = $userId;
                        }

                        if (isset($data['quitted_at']))
                            $data['quitted_at'] = null;

                        if (isset($data['type_of_employment_contract']))
                            $data['type_of_employment_contract'] = $this->transformContractType($data['type_of_employment_contract']);

                        if (isset($data['status']))
                            $data['status'] = $transformStatus($data['status']);

                        if (isset($data['role']))
                            $data['role'] = $transformRole($data['role']);

                        if (isset($data['year_paid_leave_count']))
                            $data['year_paid_leave_count'] = isset($data['year_paid_leave_count']) && $data['year_paid_leave_count'] ? $data['year_paid_leave_count'] : 0;

                        unset($data['allow_login']);
                        unset($data['username']);
                        unset($data['password']);
                        unset($data['']);

                        /***
                         * BEGIN - Check Department
                         ***/

                        $client_department =  ClientDepartment::select('id')->where([
                            'client_id' => $this->client_id,
                            'code' => $data['department']
                        ])->first();

                        if ($client_department !== NULL) {
                            $data['client_department_id'] = $client_department->id;
                            $clientEmployee->fill($data);
                            $clientEmployee->save();
                        } else {
                            $error = true;
                            $errorLabel[] = "Dòng " . $i . " - Mã phòng ban không tồn tại.";
                        }

                        /***
                         * END - Check Department
                         ***/


                        /**
                         * Begin Check position
                         */
                        $client_position = ClientPosition::select('id')->where([
                            'client_id' => $this->client_id,
                            'code' => $data['position']
                        ])->first();

                        if ($client_position !== NULL) {
                            $data['client_position_id'] = $client_position->id;
                            $clientEmployee->fill($data);
                            $clientEmployee->save();
                        } else {
                            $error = true;
                            $errorLabel[] = "Dòng " . $i . " - Mã chức vụ không tồn tại.";
                        }

                        /**
                         * End check position
                         */
                    } else {
                        $error = true;
                        $errorLabel[] = "Dòng " . $i . " - Mã Nhân viên (code) đã tồn tại.";
                    }

                    $i++;
                }

                logger("ClientEmployeeImport::collection Completed importing row " . $i);
            } else {
                $error = true;
                $errorLabel[] = "Mã Công ty không tồn tại.";
                logger("ClientEmployeeImport::collection Client is not exists");
            }
        }

        $mesg = implode('<br/>', $errorLabel);
        if ($error == true) {
            DB::rollBack();

            logger("ClientEmployeeImport::collection problem with import data to DB", [$mesg]);
            throw new CustomException(
                $mesg,
                'VALIDATION_RULES'
            );
        }

        logger("ClientEmployeeImport::collection END without error");
        DB::commit();
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 4;
    }

    /**
     * Transform a date value into a Carbon object.
     *
     * @return Carbon|null
     */
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

    public function transformContractType($value)
    {
        switch ($value) {
            case 1:
                return "khongthoihan";
            case 2:
                return "chinhthuc";
            case 3:
                return "thoivu";
            default:
            case 4:
                return "thuviec";
        }
    }

    /**
     * TODO move to model
     * Transform clientEmployee's role
     *
     * @return string
     */
    public function transformRole($value)
    {
        switch ($value) {
            default:
            case "Nhân viên":
                return "staff";
            case "Kế toán":
                return "accountant";
            case "Quản lí":
                return "manager";
        }
    }
}
