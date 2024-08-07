<?php

namespace App\Imports\Sheets;

use App\Exceptions\CustomException;
use App\Models\Client;
use App\Models\ClientDepartment;
use App\Models\ClientPosition;
use App\Models\ClientEmployee;
use App\Support\Constant;
use App\Support\ImportTrait;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ClientEmployeeBasicSheetImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;
    protected $withoutEvent = false;

    protected $creatorId = 0;

    protected $isInternal = true;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
        "sex" => ['string'],
        "date_of_birth" => ['date'],
        "nationality" => ['string'],
        "nation" => ['string'],
        "religion" => ["string"],
        "marital_status" => ['string'],
        "contact_phone_number" => ['string'],
        "id_card_number" => ['string'],
        "is_card_issue_date" => ['date'],
        "id_card_issue_place" => ['string'],
        "birth_place_city_province" => ['birth_province_exits'],
        "birth_place_district" => ['birth_district_exits'],
        "birth_place_wards" => ['birth_ward_exits'],
        "birth_place_address" => ['string'],
        "birth_place_street" => ['string'],
        "resident_city_province" => ['resident_province_exits'],
        "resident_district" => ['resident_district_exits'],
        "resident_wards" => ['resident_ward_exits'],
        "resident_address" => ['string'],
        "resident_street" => ['string'],
        "contact_city_province" => ['contact_province_exits'],
        "contact_district" => ['contact_district_exits'],
        "contact_wards" => ['contact_ward_exits'],
        "contact_address" => ['string'],
        "contact_street" => ['string'],
        "year_paid_leave_count" => ['string'],
        "is_tax_applicable" => ['number'],
        "mst_code" => ['string'],
        "number_of_dependents" => ['number'],
        "title" => ['string'],
        "position" => ['string', 'required'],
        "department" => ['string', 'required'],
        "workplace" => ['string'],
        "date_of_entry" => ["date"],
        "education_level" => ['string'],
        "educational_qualification" => ["string"],
        "major" => ["string"],
        "certificate_1" => ["string"],
        "certificate_2" => ["string"],
        "certificate_3" => ["string"],
        "certificate_4" => ["string"],
        "certificate_5" => ["string"],
        "certificate_6" => ["string"],
        "blood_group" => ["string"],
        "year_of_graduation" => ["digits:4", "integer", "min:1900"],
        "emergency_contact_name" => ["string"],
        "emergency_contact_relationship" => ["string"],
        "emergency_contact_phone" => ['string'],
        "spouse_working_at_company" => ["boolean"],
        "commuting_transportation" => ["string"],
        "vehicle_license_plate" => ["string"],
        "locker_number" => ["string"],
        "bank_account" => ['string'],
        "bank_account_number" => ['string'],
        "bank_name" => ['string'],
        "bank_branch" => ['string'],
        "bank_code" => ['string'],
        "currency" => ['string'],
        "salary" => ['number'],
        "allowance_for_responsibilities" =>  ['number'],
        "fixed_allowance" =>  ['number'],
        "is_insurance_applicable" => ['number'],
        "social_insurance_number" => ['string'],
        "effective_date_of_social_insurance" => ['string'],
        "salary_for_social_insurance_payment" => ['number'],
        "medical_care_hospital_name" => ['string'],
        "medical_care_hospital_code" => ['string'],
        "role" => ['number', 'required'],
        "allow_login" => ['number', 'required'],
        "email" => ['string'],
        "username" => ['username_exists'],
        "overwrite" => ['number'],
    ];

    function __construct($clientId, $withoutEvent = false, $creatorId = 0)
    {
        $this->client_id = $clientId;
        $this->withoutEvent = $withoutEvent;
        $this->creatorId = $creatorId;
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
                        $field = ++$key . '.' . $fieldName;
                        $dateErrors->add($field, trans('validation.date', ['attribute' => $field]));
                    }
                } else {
                    unset($data[$fieldName]);
                }
            };

            $checkDate('date_of_birth');
            $checkDate('date_of_entry');
            $checkDate('effective_date_of_social_insurance');

            foreach ($data as $k => $v) {
                if (str_ends_with($k, '_date')) {
                    $checkDate($k);
                }
            }

            return $data;
        });

        if (!$dateErrors->isEmpty()) {
            $errorText = '';
            foreach ($dateErrors->all() as $error) {
                $errorText .= $error . ' <br/> ';
            }
            logger("ClientEmployeeImport::collection date validation error", [$dateErrors]);
            throw new CustomException(
                $errorText,
                'ValidationException'
            );
        }

        DB::beginTransaction();
        $i = 4;
        if (!empty($this->client_id)) {
            $clientOj = Client::where('id', $this->client_id)->first();

            if ($clientOj) {
                $userRepo = !empty($this->creatorId) ? User::where('id', $this->creatorId)->first() : auth()->user();
                if ($userRepo->isInternalUser() && !$userRepo->iGlocalEmployee->assignments->where("client_id", $this->client_id)->first()) {
                    $error = true;
                    $errorLabel[] = "Nhân viên chưa có quyền assgin công ty";
                } else {
                    $workflowSetting = $clientOj->clientWorkflowSetting;
                    foreach ($filteredData as $row) {

                        $existClientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                            ->where('code', '=', $row['code'] . '')
                            ->first();

                        $canUpdateAllEmployee = !$existClientEmployee;

                        $overwrite = $row['overwrite'];
                        $isThirdPartyClient = $workflowSetting->enable_create_payroll;

                        if (!$existClientEmployee || $overwrite || $isThirdPartyClient) {

                            $data = $row;

                            if ($existClientEmployee) {
                                $clientEmployee = $existClientEmployee;
                            } else {
                                $clientEmployee = new ClientEmployee();

                                // prepare user import file data
                                $intData = array(
                                    "is_tax_applicable", "is_insurance_applicable", "number_of_dependents",
                                    'salary', 'allowance_for_responsibilities', 'currency', 'fixed_allowance',
                                    'salary_for_social_insurance_payment', 'social_insurance_number',
                                    'medical_care_hospital_name', 'medical_care_hospital_code', 'nationality',
                                    'nation', 'bank_account', 'bank_account_number', 'bank_name',
                                    'bank_branch', 'contact_phone_number', 'marital_status', 'id_card_number',
                                    'id_card_issue_place', 'title',
                                );

                                foreach ($intData as $d) {
                                    if (!isset($data[$d])) {
                                        $data[$d] = ($d === "is_tax_applicable" || $d === "is_insurance_applicable" || $d === "number_of_dependents") ? 0 : "";
                                    }
                                }
                            }

                            $userId = null;

                            if ($row['allow_login']) {

                                if (!isset($data['username']) || !$data['username']) {
                                    throw new CustomException(
                                        "Dòng " . $i . " - Chưa nhập tài khoản nhân viên",
                                        'ValidationExceptionUserData'
                                    );
                                }

                                $hasLogin = !empty(trim($clientEmployee->user_id));

                                $validatorUserData = Validator::make(['username' => $data['username']], [
                                    'username' => [
                                        'required',
                                        'regex:/^[a-z0-9_.]+$/',
                                        'max:255'
                                    ]
                                ]);

                                if ($validatorUserData->fails()) {
                                    $errorsMsg = $validatorUserData->errors();
                                    throw new CustomException(
                                        $errorsMsg . ' ' . $data['username'],
                                        'ValidationExceptionUserData'
                                    );
                                }

                                // Đã có tài khoản
                                if ($hasLogin) {
                                    $userId = trim($clientEmployee->user_id);

                                    $user = $clientEmployee->user;
                                    $email = trim($data['email']);
                                    if ($email) {
                                        $user->email = $email;
                                    }

                                    // Kiểm tra xem username mới đã có người dùng hay chưa nếu username mới không trùng với username cũ
                                    $newUsername = trim(strtolower($data['username']));

                                    if ($newUsername != $user->username) {
                                        $userWithNewUsername = User::where('client_id', $this->client_id)
                                            ->where('username', $this->client_id . '_' . $newUsername)
                                            ->first();

                                        if (!$userWithNewUsername) {
                                            $user->username = $newUsername;
                                        }
                                        $user->save();
                                        // if ($this->withoutEvent) {
                                        //     User::withoutEvents(function () use ($user) {
                                        //         $user->save();
                                        //     });
                                        // } else {
                                        //     $user->save();
                                        // }
                                    } else {
                                        $error = true;
                                        $errorLabel[] = "Dòng " . $i . " - Tài khoản nhân viên đã tồn tại: " . $newUsername;
                                    }

                                    // Chưa có tài khoản
                                } else {

                                    if (isset($data['username']) && $data['username']) {
                                        $userData['username'] = trim(strtolower($data['username']));
                                        $userData['password'] = bcrypt("000000"); // will be generated later
                                        $userData['name'] = $data['full_name'];
                                        $userData['email'] = trim($data['email']);
                                        $userData['is_internal'] = 0;
                                        $userData['timezone_name'] = "";
                                        $userData['client_id'] = $this->client_id;

                                        $user = User::where('client_id', $this->client_id)
                                            ->where('username', $this->client_id . '_' . $userData['username'])
                                            ->first();
                                        if (!$user) {

                                            if ($this->withoutEvent) {
                                                $user = User::withoutEvents(function () use ($userData) {
                                                    $userModel = new User($userData);
                                                    $userModel->save();
                                                });
                                                $userId = $user->id;
                                            } else {
                                                $userModel = new User($userData);
                                                $userModel->save();
                                                $userId = $userModel->id;
                                            }
                                        } else {
                                            $error = true;
                                            $errorLabel[] = "Dòng " . $i . " - Tài khoản nhân viên đã tồn tại: " . $userData['username'];
                                        }
                                    }
                                }
                            }

                            $transformRole = function ($value) {
                                $value = (int)($value);
                                $statuses = [
                                    "1" => "staff",
                                    "5" => "manager"
                                ];
                                return isset($statuses[$value]) ? $statuses[$value] : $statuses[1];
                            };

                            $data['client_id'] = $this->client_id;

                            if (!empty($data['birth_place_city_province']) || !empty($data['birth_place_district']) || !empty($data['birth_place_wards']) || !empty($data['birth_place_address'])) {

                                $buildAdress = $this->buildAddress([
                                    'address' => isset($data['birth_place_address']) ? $data['birth_place_address'] : '',
                                    'province' => isset($data['birth_place_city_province']) ? $data['birth_place_city_province'] : '',
                                    'district' => isset($data['birth_place_district']) ? $data['birth_place_district'] : '',
                                    'ward' => isset($data['birth_place_wards']) ? $data['birth_place_wards'] : '',
                                ]);

                                $data['birth_place_city_province']  = $buildAdress['province_id'];
                                $data['birth_place_district']       = $buildAdress['district_id'];
                                $data['birth_place_wards']          = $buildAdress['ward_id'];
                                $data['birth_place_street']         = $buildAdress['full_address'];
                            }

                            if (!empty($data['resident_city_province']) || !empty($data['resident_district']) || !empty($data['resident_wards']) || !empty($data['resident_address'])) {

                                $buildAdress = $this->buildAddress([
                                    'address' => isset($data['resident_address']) ? $data['resident_address'] : '',
                                    'province' => isset($data['resident_city_province']) ? $data['resident_city_province'] : '',
                                    'district' => isset($data['resident_district']) ? $data['resident_district'] : '',
                                    'ward' => isset($data['resident_wards']) ? $data['resident_wards'] : '',
                                ]);

                                $data['resident_city_province']  = $buildAdress['province_id'];
                                $data['resident_district']       = $buildAdress['district_id'];
                                $data['resident_wards']          = $buildAdress['ward_id'];
                                $data['resident_street']         = $buildAdress['full_address'];
                            }

                            if (!empty($data['contact_city_province']) || !empty($data['contact_district']) || !empty($data['contact_wards']) || !empty($data['contact_address'])) {

                                $buildAdress = $this->buildAddress([
                                    'address' => isset($data['contact_address']) ? $data['contact_address'] : '',
                                    'province' => isset($data['contact_city_province']) ? $data['contact_city_province'] : '',
                                    'district' => isset($data['contact_district']) ? $data['contact_district'] : '',
                                    'ward' => isset($data['contact_wards']) ? $data['contact_wards'] : '',
                                ]);

                                $data['contact_city_province']  = $buildAdress['province_id'];
                                $data['contact_district']       = $buildAdress['district_id'];
                                $data['contact_wards']          = $buildAdress['ward_id'];
                                $data['contact_street']         = $buildAdress['full_address'];
                            }

                            if (isset($data['household_head_date_of_birth']))
                                $data['household_head_date_of_birth'] = !empty($row['household_head_date_of_birth']) ? $this->transformDate($row['household_head_date_of_birth']) : null;
                            if ($userId) {
                                $data['user_id'] = $userId;
                            }

                            if (isset($data['quitted_at']))
                                $data['quitted_at'] = null;


                            if (isset($data['role']) && $canUpdateAllEmployee) {
                                $data['role'] = $transformRole($data['role']);
                            } else {
                                unset($data['role']);
                            }

                            if (!$existClientEmployee) {
                                $data['year_paid_leave_count'] = 0;
                            }

                            unset($data['allow_login']);
                            unset($data['username']);
                            unset($data['password']);
                            unset($data['']);

                            if (!$userRepo->isInternalUser() && $userRepo->getRole() != Constant::ROLE_CLIENT_MANAGER && !$userRepo->hasDirectPermission('manage-payroll') && !$userRepo->hasDirectPermission('manage-employee-payroll')) {
                                unset($data['salary']);
                                unset($data['allowance_for_responsibilities']);
                                unset($data['fixed_allowance']);
                                unset($data['salary_for_social_insurance_payment']);
                            }

                            if (empty($data['spouse_working_at_company'])) {
                                $data['spouse_working_at_company'] = false;
                            }

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
                                try {
                                    $clientEmployee->save();
                                } catch (\Exception $e) {
                                    logger("hehe:" . $e->getMessage());
                                    $error = true;
                                    $errorLabel[] = "{$clientEmployee->code} - Lưu thất bại.";
                                }
                            } else {
                                $error = true;
                                $errorLabel[] = "Dòng " . $i . " - Mã phòng ban không tồn tại.";
                            }

                            /***
                             * END - Check Department
                             ***/
                            /***
                             * BEGIN - Check Position
                             ***/

                            $client_position =  ClientPosition::select('id')->where([
                                'client_id' => $this->client_id,
                                'code' => $data['position']
                            ])->first();

                            if ($client_position !== NULL) {
                                $data['client_position_id'] = $client_position->id;
                                $clientEmployee->fill($data);
                                try {
                                    $clientEmployee->save();
                                } catch (\Exception $e) {
                                    $error = true;
                                    $errorLabel[] = "{$clientEmployee->code} - Lưu thất bại.";
                                }
                            } else {
                                $error = true;
                                $errorLabel[] = "Dòng " . $i . " - Mã chức vụ không tồn tại.";
                            }

                            /***
                             * END - Check Position
                             ***/
                        } else {
                            $error = true;
                            $errorLabel[] = "Dòng " . $i . " - Mã Nhân viên (code) đã tồn tại.";
                        }

                        $i++;
                    }

                    logger("ClientEmployeeImport::collection Completed importing row " . $i);
                }
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
        return 75;
    }

    public function getRightHeader()
    {
        $data = self::RIGHT_HEADER;
        if (auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-payroll') && auth()->user()->getRole() != Constant::ROLE_CLIENT_MANAGER && !auth()->user()->hasDirectPermission('manage-employee-payroll')) {
            unset($data['salary']);
            unset($data['allowance_for_responsibilities']);
            unset($data['fixed_allowance']);
            unset($data['salary_for_social_insurance_payment']);
        }
        return $data;
    }

    public function getClientID()
    {
        return $this->client_id;
    }

    /**
     * Transform a date value into a Carbon object.
     *
     * @return Carbon|null
     */
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

        if (empty($this->client_id)) return;

        $clientOj = Client::find($this->client_id);

        if (!$clientOj) return;

        $workflowSetting = $clientOj->clientWorkflowSetting;

        foreach ($filteredData as $index => $row) {
            $colIndex = 1;
            $rowIndex = $index;
            $resultBirthAddress = [];
            $resultResidentAddress = [];
            $resultContactAddress = [];
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
                            if ($value && !is_numeric($value)) {
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
                        case 'birth_province_exits':
                            if ($value) {
                                $this->validateExitAddress($resultBirthAddress, $value, 'province_exits');
                                if (!isset($resultBirthAddress['province_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this province is not exists'];
                                }
                            }
                            break;
                        case 'birth_district_exits':
                            if ($value) {
                                $this->validateExitAddress($resultBirthAddress, $value, 'district_exits');
                                if (!isset($resultBirthAddress['district_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this district is not exists'];
                                }
                            }
                            break;
                        case 'birth_ward_exits':
                            if ($value) {
                                $this->validateExitAddress($resultBirthAddress, $value, 'ward_exits');
                                if (!isset($resultBirthAddress['ward_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this ward is not exists'];
                                }
                            }
                            break;
                        case 'resident_province_exits':
                            if ($value) {
                                $this->validateExitAddress($resultResidentAddress, $value, 'province_exits');
                                if (!isset($resultResidentAddress['province_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this province is not exists'];
                                }
                            }
                            break;
                        case 'resident_district_exits':
                            if ($value) {
                                $this->validateExitAddress($resultResidentAddress, $value, 'district_exits');
                                if (!isset($resultResidentAddress['district_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this district is not exists'];
                                }
                            }
                            break;
                        case 'resident_ward_exits':
                            if ($value) {
                                $this->validateExitAddress($resultResidentAddress, $value, 'ward_exits');
                                if (!isset($resultResidentAddress['ward_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this ward is not exists'];
                                }
                            }
                            break;
                        case 'contact_province_exits':
                            if ($value) {
                                $this->validateExitAddress($resultContactAddress, $value, 'province_exits');
                                if (!isset($resultContactAddress['province_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this province is not exists'];
                                }
                            }
                            break;
                        case 'contact_district_exits':
                            if ($value) {
                                $this->validateExitAddress($resultContactAddress, $value, 'district_exits');
                                if (!isset($resultContactAddress['district_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this district is not exists'];
                                }
                            }
                            break;
                        case 'contact_ward_exits':
                            if ($value) {
                                $this->validateExitAddress($resultContactAddress, $value, 'ward_exits');
                                if (!isset($resultContactAddress['ward_id'])) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $col, 'error' => 'this ward is not exists'];
                                }
                            }
                            break;
                        default:
                            break;
                    }

                    if ($col === 'username') {
                        // Validate
                        $isValid = true;
                        $existClientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                            ->where('code', '=', $row['code'] . '')
                            ->first();
                        $overwrite = $row['overwrite'];
                        $isThirdPartyClient = $workflowSetting->enable_create_payroll;

                        if (!$existClientEmployee || $overwrite || $isThirdPartyClient) {
                            $data = $row;

                            if ($existClientEmployee) {
                                $clientEmployee = $existClientEmployee;
                            } else {
                                $clientEmployee = new ClientEmployee;
                            }

                            if ($row['allow_login']) {
                                $validatorEmail = Validator::make(['email' => $data['email']], [
                                    'email' => 'required|max:255|email'
                                ]);

                                if ($validatorEmail->fails()) {
                                    $emailColIndex = array_search('email', array_keys($row)) ? array_search('email', array_keys($row)) + 1 : -1;
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $emailColIndex, 'name' => 'email', 'error' => 'Email không hợp lệ'];
                                    $isValid = false;
                                    // continue;
                                }

                                $validatorUserData = Validator::make(['username' => $data['username']], [
                                    'username' => [
                                        'required',
                                        'regex:/^[a-z0-9_.]+$/',
                                        'max:255'
                                    ]
                                ]);

                                if ($validatorUserData->fails()) {
                                    $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => 'username', 'error' => 'Tài khoản không hợp lệ'];
                                    $isValid = false;
                                }

                                // Đã có tài khoản
                                $hasLogin = !empty(trim($clientEmployee->user_id));
                                if ($hasLogin) {
                                    $user = $clientEmployee->user;
                                    $newUsername = $this->client_id . '_' . trim($data['username']);
                                    $checkNewUserNameExist = User::where('username', $newUsername)->exists();
                                    if ($checkNewUserNameExist && $user->username !== $newUsername) {
                                        $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => 'username', 'error' => 'Tài khoản nhân viên đã tồn tại'];
                                        $isValid = false;
                                    }

                                    // Chưa có tài khoản
                                } else {
                                    if (isset($data['username']) && $data['username']) {
                                        $userData['username'] = trim(strtolower($data['username']));
                                        $userData['name'] = $data['full_name'];
                                        $user = User::where('client_id', $this->client_id)
                                            ->where('username', $this->client_id . '_' . $userData['username'])
                                            ->first();
                                        if ($user) {
                                            $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => 'username', 'error' => 'Tài khoản nhân viên đã tồn tại'];
                                            $isValid = false;
                                        }
                                    }
                                }
                            }
                        } else {
                            $errorFormats[] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => 'username', 'error' => 'Mã Nhân viên (code) đã tồn tại.'];
                            $isValid = false;
                        }

                        if (!$isValid) {
                            continue;
                        }
                    }
                }

                $colIndex++;
            }
        }

        if ($errorFormats)
            $errors['formats'] = $errorFormats;

        if ($errors) {
            $errors['startRow'] = $this->startRow();
        }

        return $errors;
    }


    // public function validate($sheet)
    // {
    //     $RIGHT_HEADER = $this->getRightHeader();
    //     $data = $this->getData($sheet);

    //     if (!$data) {
    //         return [];
    //     }

    //     $errors = [];
    //     $header = array_values($data['header']);
    //     $filteredData = $data['rows'];
    //     $HEADER = array_keys($RIGHT_HEADER);
    //     $diffCols = array_diff($HEADER, $header);
    //     $missingCols = [];
    //     $errorFormats = [];

    //     foreach ($diffCols as $c) {
    //         if (!isset($HEADER[$c])) {
    //             $missingCols[] = $c;
    //         }
    //     }

    //     if ($missingCols) {
    //         $errors['missing_cols'] = $missingCols;
    //     }

    //     $hasLogin = false;
    //     $validator = Validator::make([], []);

    //     if (!empty($this->client_id)) {
    //         $clientOj = Client::find($this->client_id);

    //         if ($clientOj) {
    //             $workflowSetting = $clientOj->clientWorkflowSetting;

    //             foreach ($filteredData as $index => &$row) {
    //                 $colIndex = 1;
    //                 $rowIndex = $index;

    //                 foreach ($row as $col => &$value) {
    //                     if (isset($RIGHT_HEADER[$col])) {
    //                         $required = isset($RIGHT_HEADER[$col][1]) && ($RIGHT_HEADER[$col][1] == 'required');
    //                         if ($required && is_null($value)) {
    //                             $errorFormats[] = [
    //                                 'row' => $rowIndex,
    //                                 'col' => $colIndex,
    //                                 'name' => $col,
    //                                 'error' => 'Field is required',
    //                             ];
    //                         } else {
    //                             switch ($RIGHT_HEADER[$col][0]) {
    //                                 case 'number':
    //                                     if (!is_numeric($value)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Invalid number format',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 case 'date':
    //                                     if ($value && !$this->isDate($value)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Invalid date format',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 case 'code_employee_exists':
    //                                     if (!$this->hasEmployee($value)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Employee code does not exist',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 case 'code_employee_not_exists':
    //                                     if ($this->hasEmployee($value)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Employee code already exists',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 case 'username_exists':
    //                                     if (!$hasLogin && !$this->checkUsername($row)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Username already exists',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 case 'country_exists':
    //                                     if (!in_array($row['nationality'], Constant::COUNTRY_LIST)) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => $col,
    //                                             'error' => 'Nationality does not exist',
    //                                         ];
    //                                     }
    //                                     break;
    //                                 default:
    //                                     break;
    //                             }

    //                             if ($col === 'username') {
    //                                 $hasLogin = true;

    //                                 if ($row['allow_login']) {
    //                                     $validator->setData(['email' => $row['email']]);
    //                                     $validator->setRules(['email' => 'required|max:255|email']);

    //                                     if ($validator->fails()) {
    //                                         $emailColIndex = array_search('email', array_keys($row)) ? array_search('email', array_keys($row)) + 1 : -1;
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $emailColIndex,
    //                                             'name' => 'email',
    //                                             'error' => 'Invalid email',
    //                                         ];
    //                                     }

    //                                     $validator->setData(['username' => $row['username']]);
    //                                     $validator->setRules(['username' => 'required|max:255|alpha_dash']);

    //                                     if ($validator->fails()) {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => 'username',
    //                                             'error' => 'Invalid username',
    //                                         ];
    //                                     }

    //                                     $existClientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
    //                                         ->where('code', '=', $row['code'] . '')
    //                                         ->first();

    //                                     if (!$existClientEmployee || $row['overwrite'] || $workflowSetting->enable_create_payroll) {
    //                                         $data = $row;
    //                                         $clientEmployee = $existClientEmployee ?: new ClientEmployee;

    //                                         if ($hasLogin) {
    //                                             $user = $clientEmployee->user;
    //                                             $newUsername = $this->client_id . '_' . trim($data['username']);
    //                                             $checkNewUserNameExist = User::where('username', $newUsername)->exists();

    //                                             if ($checkNewUserNameExist && $user->username !== $newUsername) {
    //                                                 $errorFormats[] = [
    //                                                     'row' => $rowIndex,
    //                                                     'col' => $colIndex,
    //                                                     'name' => 'username',
    //                                                     'error' => 'Employee username already exists',
    //                                                 ];
    //                                             }
    //                                         } else {
    //                                             if (isset($data['username']) && $data['username']) {
    //                                                 $userData['username'] = trim(strtolower($data['username']));
    //                                                 $userData['name'] = $data['full_name'];
    //                                                 $user = User::where('client_id', $this->client_id)
    //                                                     ->where('username', $this->client_id . '_' . $userData['username'])
    //                                                     ->first();

    //                                                 if ($user) {
    //                                                     $errorFormats[] = [
    //                                                         'row' => $rowIndex,
    //                                                         'col' => $colIndex,
    //                                                         'name' => 'username',
    //                                                         'error' => 'Employee username already exists',
    //                                                     ];
    //                                                 }
    //                                             }
    //                                         }
    //                                     } else {
    //                                         $errorFormats[] = [
    //                                             'row' => $rowIndex,
    //                                             'col' => $colIndex,
    //                                             'name' => 'username',
    //                                             'error' => 'Employee code already exists',
    //                                         ];
    //                                     }
    //                                 }
    //                             }
    //                         }
    //                     }

    //                     $colIndex++;
    //                 }
    //             }
    //         }
    //     }

    //     if ($errorFormats) {
    //         $errors['formats'] = $errorFormats;
    //     }

    //     if ($errors) {
    //         $errors['startRow'] = $this->startRow();
    //     }

    //     return $errors;
    // }
}
