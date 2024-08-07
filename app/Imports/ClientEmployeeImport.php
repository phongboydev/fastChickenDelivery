<?php

namespace App\Imports;

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
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

use App\Models\Client;
use App\Models\ClientEmployee;
use App\User;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ClientEmployeeImportErrorExport;

class ClientEmployeeImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable;

    const VALIDATION_RULES = [
        '*.code'                                    => 'sometimes',
        '*.full_name'                               => 'required_with:code',
        '*.type_of_employment_contract'             => 'required_with:code||in:1,2,3,4',
        '*.salary'                                  => 'required_with:code',
        '*.is_tax_applicable'                       => 'required_with:code|in:0,1,2,3',
        '*.is_insurance_applicable'                 => 'required_with:code|in:0,1,2,3,4,5,6,7',
        '*.number_of_dependents'                    => 'required_with:code|integer',
        '*.bank_name'                               => 'nullable',
        '*.bank_branch'                             => 'nullable',
        '*.date_of_birth'                           => 'required_with:code',
        '*.sex'                                     => 'required_with:code',
        '*.department'                              => 'nullable',
        '*.position'                                => 'required',
        '*.title'                                   => 'required',
        '*.workplace'                               => 'required',
        '*.marital_status'                          => 'required',
        '*.effective_date_of_social_insurance'      => 'exclude_if:effective_date_of_social_insurance,|date',
        '*.medical_care_hospital_name'              => 'nullable',
        '*.nationality'                             => 'required',
        '*.nation'                                  => 'required',
        '*.id_card_number'                          => 'required',
        '*.is_card_issue_date'                      => 'required',
        '*.id_card_issue_place'                     => 'required',
        '*.birth_place_address'                     => 'nullable',
        '*.birth_place_street'                      => 'nullable',
        '*.birth_place_wards'                       => 'nullable',
        '*.birth_place_district'                    => 'nullable',
        '*.birth_place_city_province'               => 'nullable',
        '*.resident_address'                        => 'nullable',
        '*.resident_street'                         => 'nullable',
        '*.resident_wards'                          => 'nullable',
        '*.resident_district'                       => 'nullable',
        '*.resident_city_province'                  => 'nullable',
        '*.contact_address'                         => 'nullable',
        '*.contact_street'                          => 'nullable',
        '*.contact_wards'                           => 'nullable',
        '*.contact_district'                        => 'nullable',
        '*.contact_city_province'                   => 'nullable',
        '*.household_head_info'                     => 'nullable',
        '*.household_head_fullname'                 => 'nullable',
        '*.household_head_relation'                 => 'nullable',
        '*.resident_record_type'                    => 'nullable',
        '*.resident_village'                        => 'nullable',
        '*.resident_commune_ward_district_province' => 'nullable',
        '*.probation_start_date'                    => 'nullable',
        '*.probation_end_date'                      => 'nullable',
        '*.official_contract_signing_date'          => 'nullable',
        '*.contract_no'                             => 'nullable',
        '*.career'                                  => 'nullable',
        '*.mst_code'                                => 'nullable',
        '*.allow_login'                             => 'sometimes',
        '*.role'                                    => 'exclude_unless:allow_login,1|sometimes|in:Nhân viên,Kế toán,Quản lí',
        '*.status'                                  => 'required|in:1,2,3,4',
        '*.overwrite'                               => 'exclude_if:effective_date_of_social_insurance,|in:0,1',
    ];

    protected $client_id = null;

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    /**
     * @param Collection $rows
     * @throws Exception
     */
    public function collection(Collection $rows)
    {
        logger("ClientEmployeeImport::collection BEGIN");
        $error = false;
        $errorLabel = array();

        if (!$this->validateAll($rows)) {
            $errorFile = 'ClientEmployeeImport/client_employee_import_error_export.xlsx';

            Excel::store((new ClientEmployeeImportErrorExport(
                $this->client_id,
                $rows
            )), $errorFile, 'minio');
        } else {

            $filteredData = collect([]);

            foreach ($rows as $key => $row) {
                $allColsIsEmpty = empty(array_filter($row->toArray(), function ($v) {
                    return !empty($v);
                }));
                if (!$allColsIsEmpty) {
                    $filteredData->push($row);
                }
            }

            $validator = Validator::make($filteredData->toArray(), self::VALIDATION_RULES);
            if ($validator->fails()) {
                $errorsMsg = $validator->errors();
                logger("ClientEmployeeImport::collection validation error", [$errorsMsg]);
                throw new CustomException(
                    $errorsMsg,
                    'ValidationException'
                );
            }

            logger('@$filteredData', [$filteredData]);

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

                $checkDate('probation_start_date');
                $checkDate('probation_end_date');
                $checkDate('official_contract_signing_date');
                $checkDate('date_of_birth');
                $checkDate('effective_date_of_social_insurance');
                $checkDate('is_card_issue_date');
                $checkDate('date_of_entry');
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

                        // logger("ClientEmployeeImport::collection loop filteredData", [$row]);

                        $existClientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                            ->where('code', '=', $row['code'])
                            ->first();

                        // logger('$existClientEmployee', [!$existClientEmployee]);
                        $overwrite = $row['overwrite'] ?? false;

                        if (!$existClientEmployee || $overwrite) {

                            if ($existClientEmployee) {
                                $clientEmployee = $existClientEmployee;
                            } else {
                                $clientEmployee = new ClientEmployee();
                            }

                            $data = $row;

                            // prepare user import file data
                            foreach ($data as $key => $value) {
                                $intData = array("is_tax_applicable", "is_insurance_applicable", "number_of_dependents");
                                if (!in_array($key, $intData)) {
                                    if (empty($value))
                                        $data[$key] = "";
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
                                            'username' => [
                                                'required',
                                                'regex:/^[a-z0-9_.]+$/',
                                                'max:255'
                                            ]
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
                            $data['household_head_date_of_birth'] = !empty($row['household_head_date_of_birth']) ? $this->transformDate($row['household_head_date_of_birth']) : null;
                            if ($userId) {
                                $data['user_id'] = $userId;
                            }
                            $data['quitted_at'] = null;
                            $data['type_of_employment_contract'] = $this->transformContractType($data['type_of_employment_contract']);
                            $data['status'] = $transformStatus($data['status']);
                            $data['role'] = $transformRole($data['role']);

                            $data['year_paid_leave_count'] = isset($data['year_paid_leave_count']) && $data['year_paid_leave_count'] ? $data['year_paid_leave_count'] : 0;

                            unset($data['allow_login']);
                            unset($data['username']);
                            unset($data['password']);
                            unset($data['']);

                            $data = $data->toArray();

                            $clientEmployee->fill($data);
                            $clientEmployee->save();
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
    }

    public function rules(): array
    {
        return self::VALIDATION_RULES;
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

    public function validateAll($rows)
    {

        return true;
    }
}
