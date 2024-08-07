<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use App\Models\Approve;
use App\User;
use App\Models\ClientEmployeeContract;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeDependent;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeSalaryHistory;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Rules\UserCodeExistsRule;
use App\Rules\UsernameExistsAvailableRule;
use App\Rules\UserCodeAvailableRule;
use App\Rules\SalaryHistoryRule;
use App\Rules\LeaveRule;
use Illuminate\Support\Arr;
use App\Support\LeaveHelper;

class ImportHelper
{

    const CLIENT_EMPLOYEE = 'client_employee';
    const SALARY_INFORMATION = 'salary_information';
    const PAID_LEAVE = 'paid_leave';
    const CONTRACT_INFORMATION = 'contract_information';
    const DEPENDANT_INFORMATION = 'dependant_information';
    const AUTHORIZED_LEAVE = 'authorized_leave';
    const UNAUTHORIZED_LEAVE = 'unauthorized_leave';

    const TOTAL_COLUMNS = [
        self::CLIENT_EMPLOYEE => 71,
        self::SALARY_INFORMATION => 11,
        self::PAID_LEAVE => 6,
        self::CONTRACT_INFORMATION => 19,
        self::DEPENDANT_INFORMATION => 8,
        self::AUTHORIZED_LEAVE => 11,
        self::UNAUTHORIZED_LEAVE => 11
    ];

    const HEADERS_LIST = [
        self::CLIENT_EMPLOYEE => [
            "code" => "B",
            "full_name" => "C",
            "sex" => "D",
            "date_of_birth" => "E",
            "nationality" => "F",
            "nation" => "G",
            "religion" => "H",
            "marital_status" => "I",
            "contact_phone_number" => "J",
            "id_card_number" => "K",
            "is_card_issue_date" => "L",
            "id_card_issue_place" => "M",
            "birth_place_city_province" => "N",
            "birth_place_district" => "O",
            "birth_place_wards" => "P",
            "birth_place_address" => "Q",
            "birth_place_street" => "R",
            "resident_city_province" => "S",
            "resident_district" => "T",
            "resident_wards" => "U",
            "resident_address" => "V",
            "resident_street" => "W",
            "contact_city_province" => "X",
            "contact_district" => "Y",
            "contact_wards" => "Z",
            "contact_address" => "AA",
            "contact_street" => "AB",
            "is_tax_applicable" => "AC",
            "mst_code" => "AD",
            "number_of_dependents" => "AE",
            "title" => "AF",
            "position" => "AG",
            "department" => "AH",
            "workplace" => "AI",
            "date_of_entry" => "AJ",
            "education_level" => "AK",
            "educational_qualification" => "AL",
            "major" => "AM",
            "certificate_1" => "AN",
            "certificate_2" => "AO",
            "certificate_3" => "AP",
            "certificate_4" => "AQ",
            "certificate_5" => "AR",
            "certificate_6" => "AS",
            "year_of_graduation" => "AT",
            "blood_group" => "AU",
            "emergency_contact_name" => "AV",
            "emergency_contact_relationship" => "AW",
            "emergency_contact_phone" => "AX",
            "spouse_working_at_company" => "AY",
            "commuting_transportation" => "AZ",
            "vehicle_license_plate" => "BA",
            "locker_number" => "BB",
            "year_paid_leave_count" => "BC",
            "bank_account" => "BD",
            "bank_account_number" => "BE",
            "bank_code" => "BF",
            "bank_name" => "BG",
            "bank_branch" => "BH",
            "is_insurance_applicable" => "BI",
            "social_insurance_number" => "BJ",
            "effective_date_of_social_insurance" => "BK",
            "salary_for_social_insurance_payment" => "BL",
            "medical_care_hospital_name" => "BM",
            "medical_care_hospital_code" => "BN",
            "role" => "BO",
            "allow_login" => "BP",
            "email" => "BQ",
            "username" => "BR",
            "overwrite" => "BS"
        ],
        self::SALARY_INFORMATION => [
            'code' => 'B',
            'full_name' => 'C',
            "currency" => "D",
            "old_salary" => "E",
            "salary" => "F",
            "old_allowance_for_responsibilities" => "G",
            "allowance_for_responsibilities" => "H",
            "old_fixed_allowance" => "I",
            "fixed_allowance" => "J",
            "effective_date" => "K"
        ],
        self::PAID_LEAVE => [
            "code" => "B",
            "full_name" => "C",
            "start_import_paidleave" => "D",
            "hours_import_paidleave" => "E",
            "type" => "F"
        ],
        self::DEPENDANT_INFORMATION => [
            "code" => "B",
            "full_name" => "C",
            "name_dependents" => "D",
            "tax_code" => "E",
            "relationship_code" => "F",
            "from_date" => "G",
            "to_date" => "H"
        ],
        self::CONTRACT_INFORMATION => [
            "code" => "B",
            "full_name" => "C",
            "status" => "D",
            "type_of_employment_contract" => "E",
            "contract_no_1" => "F",
            "probation_start_date" => "G",
            "probation_end_date" => "H",
            "contract_no_2" => "I",
            "definite_term_contract_first_time_start_date" => "J",
            "definite_term_contract_first_time_end_date" => "K",
            "contract_no_3" => "L",
            "definite_term_contract_second_time_start_date" => "M",
            "definite_term_contract_second_time_end_date" => "N",
            "contract_no_4" => "O",
            "indefinite_term_contract_start_date" => "P",
            "contract_no_5" => "Q",
            "other_term_contract_start_date" => "R",
            "resignation_date" => "S",
        ],
        self::AUTHORIZED_LEAVE => [
            "code" => "B",
            "full_name" => "C",
            "self_marriage_leave" => "D",
            "child_marriage_leave" => "E",
            "family_lost" => "F",
            "woman_leave" => "G",
            "baby_care" => "H",
            "changed_leave" => "I",
            "covid_leave" => "J",
            "other_leave" => "K",
            "action" => "L"
        ],
        self::UNAUTHORIZED_LEAVE => [
            "code" => "B",
            "full_name" => "C",
            "unpaid_leave" => "D",
            "pregnant_leave" => "E",
            "self_sick_leave" => "F",
            "child_sick" => "G",
            "wife_pregnant_leave" => "H",
            "prenatal_checkup_leave" => "I",
            "sick_leave" => "J",
            "other_leave" => "K",
            "action" => "L"
        ]
    ];

    const BLOOD_GROUPS = [
        'A_POSITIVE' => 'A+',
        'A_NEGATIVE' => 'A-',
        'B_POSITIVE' => 'B+',
        'B_NEGATIVE' => 'B-',
        'AB_POSITIVE' => 'AB+',
        'AB_NEGATIVE' => 'AB-',
        'O_POSITIVE' => 'O+',
        'O_NEGATIVE' => 'O-',
        'UNKNOWN' => 'Unknown',
    ];

    public static function getStartRow($type)
    {
        $startRows = [
            self::CLIENT_EMPLOYEE => [
                "data" => 4,
                "header" => 3
            ],
            self::SALARY_INFORMATION => [
                "data" => 3,
                "header" => 2
            ],
            self::PAID_LEAVE => [
                "data" => 3,
                "header" => 2
            ],
            self::CONTRACT_INFORMATION => [
                "data" => 4,
                "header" => 3
            ],
            self::DEPENDANT_INFORMATION => [
                "data" => 3,
                "header" => 2
            ],
            self::AUTHORIZED_LEAVE => [
                "data" => 4,
                "header" => 3
            ],
            self::UNAUTHORIZED_LEAVE => [
                "data" => 4,
                "header" => 3
            ]
        ];

        return $startRows[$type] ?? null;
    }

    const CLIENT_EMPLOYEE_FORMAT_DATE = ['date_of_birth', 'is_card_issue_date', 'date_of_entry', 'effective_date_of_social_insurance'];
    const CONTRACT_INFORMATION_FORMAT_DATE = [
        'probation_start_date', 'probation_end_date',
        'definite_term_contract_first_time_start_date', 'definite_term_contract_first_time_end_date',
        'definite_term_contract_second_time_start_date', 'definite_term_contract_second_time_end_date',
        'indefinite_term_contract_start_date', 'other_term_contract_start_date',
        'resignation_date'
    ];

    const EDUCATION_LEVEL = ['university', 'college', 'intermediate', 'elementary_occupations', 'vocational_training_regularly', 'untrained'];

    const PROVINCES = ['birth_place_city_province', 'resident_city_province', 'contact_city_province'];
    const DISTRICTS = ['birth_place_district', 'resident_district', 'contact_district'];
    const WARDS = ['birth_place_wards', 'resident_wards', 'contact_wards'];

    const CLIENT_EMPLOYEE_INIT_DATA = [
        'salary_for_social_insurance_payment', 'social_insurance_number', 'medical_care_hospital_name', 'medical_care_hospital_code', 'nationality', 'nation',
        'bank_account', 'bank_account_number', 'bank_name', 'bank_branch', 'contact_phone_number',
        'id_card_number', 'id_card_issue_place', 'title', "marital_status",
    ];

    const SALARY_INFORMATION_INIT_DATA = [
        'salary', 'allowance_for_responsibilities', 'currency', 'fixed_allowance'
    ];

    const CLIENT_EMPLOYEE_INIT_DEFAULT = [
        "spouse_working_at_company", "is_tax_applicable", "is_insurance_applicable", "number_of_dependents"
    ];

    public static function getCode($name)
    {
        return Str::between($name, '(', ')');
    }

    public static function isValidDateOrDatetime($value)
    {
        if ($value instanceof Carbon) {
            return $value;
        } else {
            return "";
        }
    }

    public static function checkApproveRequest($authId, $clientId, $contentType, $approveType)
    {
        return Approve::where(['type' => $approveType, 'original_creator_id' => $authId, 'content->client_id' => $clientId, 'content->type' => $contentType, 'approved_at' => null, 'declined_at' => null])->exists();
    }

    public static function internalCreateApprove($authId, $client, $clientId, $data, $step, $approveGroupId, $assigneeId, $contentType, $approveType)
    {
        try {
            DB::transaction(
                function () use ($authId, $client, $clientId, $data, $step, $approveGroupId, $assigneeId, $contentType, $approveType) {
                    Approve::create([
                        'type' => $approveType,
                        'content' => json_encode([
                            'client_id' => $clientId,
                            'company_name' => $client->company_name,
                            'code' => $client->code,
                            'data' => $data,
                            'type' => $contentType,
                        ]),
                        'creator_id' => $authId,
                        'original_creator_id' => $authId,
                        'step' => $step,
                        'is_final_step' => 0,
                        'client_id' => Constant::INTERNAL_DUMMY_CLIENT_ID,
                        'approve_group_id' => $approveGroupId,
                        'assignee_id' => $assigneeId,
                        'client_employee_group_id' => 0,
                        'source' => Constant::WEB
                    ]);
                }
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function validatorDuplicate($validator, &$dataValidate, $headersList)
    {
        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $i => $error) {
                $alpha = Str::of($i)->afterLast('.')->__toString();
                $num = (int)Str::of($i)->beforeLast('.')->__toString();
                $fieldName = ($alpha == 'ordinal_number') ? "A" . ($num + 1) : $headersList[$alpha] . ($num + 1);
                $groupedDuplicates[$error[0]][] = $fieldName;
            }

            // Build a message for each group of duplicates
            foreach ($groupedDuplicates as $errorMessage => $fields) {
                $errorMessageFields = $errorMessage . " - " . implode(',', $fields);
                $dataValidate['validate'] = array_merge($dataValidate['validate'], array_fill_keys($fields, $errorMessageFields));
            }
        }
    }

    public static function updateContract($row, $clientId)
    {
        $row = collect($row)->except(['ordinal_number'])->mapWithKeys(function ($value, $key) {
            $statusMapping = [
                1 => Constant::CLIENT_EMPLOYEE_STATUS_WORKING,
                2 => Constant::CLIENT_EMPLOYEE_STATUS_UNPAID,
                3 => Constant::CLIENT_EMPLOYEE_STATUS_MATERNITY,
                4 => Constant::CLIENT_EMPLOYEE_STATUS_QUIT,
            ];

            $contractTypeMapping = [
                1 => "khongthoihan",
                2 => "chinhthuc",
                3 => "thoivu",
                4 => "thuviec",
            ];

            if ($key === 'status' && isset($statusMapping[$value])) {
                return [$key => $statusMapping[$value]];
            }

            if ($key === 'type_of_employment_contract' && isset($contractTypeMapping[$value])) {
                return [$key => $contractTypeMapping[$value]];
            }

            return [$key => $value];
        })->toArray();

        $existClientEmployee = ClientEmployee::where(['client_id' => $clientId, 'code' => $row['code']])->first();

        DB::transaction(function () use ($row, $existClientEmployee) {
            $existClientEmployee->update(['status' => $row['status'], 'type_of_employment_contract' => $row['type_of_employment_contract']]);

            if (isset($row['contract_no_1']) && !is_null($row['contract_no_1'])) {

                $data = ['contract_code' => $row['contract_no_1'], 'contract_type' => 'thuviec'];

                if (isset($row['probation_start_date']) && $row['probation_start_date']) {
                    $data['contract_signing_date'] = $row['probation_start_date'];
                }

                if (isset($row['probation_end_date']) && $row['probation_end_date']) {
                    $data['contract_end_date'] = $row['probation_end_date'];
                }

                ClientEmployeeContract::updateOrCreate(
                    ['client_employee_id' => $existClientEmployee->id, 'contract_code' => $row['contract_no_1'], 'contract_type' => 'thuviec'],
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
                    ['client_employee_id' => $existClientEmployee->id, 'contract_code' => $row['contract_no_2'], 'contract_type' => 'co-thoi-han-lan-1'],
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
                    ['client_employee_id' => $existClientEmployee->id, 'contract_code' => $row['contract_no_3'], 'contract_type' => 'co-thoi-han-lan-2'],
                    $data
                );
            }

            if (isset($row['contract_no_4']) && !is_null($row['contract_no_4'])) {

                $data = ['contract_code' => $row['contract_no_4'], 'contract_type' => 'khong-xac-dinh-thoi-han'];

                if (isset($row['indefinite_term_contract_start_date']) && $row['indefinite_term_contract_start_date']) {
                    $data['contract_signing_date'] = $row['indefinite_term_contract_start_date'];
                }

                ClientEmployeeContract::updateOrCreate(
                    ['client_employee_id' => $existClientEmployee->id, 'contract_code' => $row['contract_no_4'], 'contract_type' => 'khong-xac-dinh-thoi-han'],
                    $data
                );
            }
        });
    }

    public static function updateSalary($row, $clientId)
    {
        $row = collect($row)->except(['ordinal_number'])->mapWithKeys(function ($value, $key) {
            return [$key => $value];
        })->toArray();

        $existClientEmployee = ClientEmployee::select('id', 'currency', 'allowance_for_responsibilities', 'currency', 'fixed_allowance', 'salary')
            ->where(['client_id' => $clientId, 'code' => $row['code']])
            ->first();

        if ($existClientEmployee) {
            $existClientEmployee->load('currentSalary');
            $effectiveDate = Carbon::parse($row['effective_date']);

            if (empty($existClientEmployee->currentSalary)) {
                $data = [
                    'client_employee_id' => $existClientEmployee->id,
                    'old_salary' => $existClientEmployee->salary,
                    'new_salary' => $row['salary'] ?? $existClientEmployee->salary,
                    'old_fixed_allowance' => $existClientEmployee->fixed_allowance,
                    'new_fixed_allowance' => $row['fixed_allowance'] ?? $existClientEmployee->fixed_allowance,
                    'old_allowance_for_responsibilities' => $existClientEmployee->allowance_for_responsibilities,
                    'new_allowance_for_responsibilities' => $row['allowance_for_responsibilities'] ?? $existClientEmployee->allowance_for_responsibilities,
                    'start_date' => $row['effective_date'],
                ];
            } else {
                $data = [
                    'client_employee_id' => $existClientEmployee->id,
                    'old_salary' => $existClientEmployee->currentSalary->new_salary,
                    'new_salary' => $row['salary'] ?? $existClientEmployee->currentSalary->new_salary,
                    'old_fixed_allowance' => $existClientEmployee->currentSalary->new_fixed_allowance,
                    'new_fixed_allowance' => $row['fixed_allowance'] ?? $existClientEmployee->currentSalary->new_fixed_allowance,
                    'old_allowance_for_responsibilities' => $existClientEmployee->currentSalary->new_allowance_for_responsibilities,
                    'new_allowance_for_responsibilities' => $row['allowance_for_responsibilities'] ?? $existClientEmployee->currentSalary->new_allowance_for_responsibilities,
                    'start_date' => $row['effective_date'],
                ];
            }

            DB::beginTransaction();
            try {
                if (
                    $effectiveDate->lessThanOrEqualTo(Carbon::now()->format('Y-m-d'))
                    && (empty($existClientEmployee->currentSalary->start_date)
                        || $effectiveDate->isAfter($existClientEmployee->currentSalary->start_date)
                    )
                ) {
                    $existClientEmployee->salary = $row['salary'] ?? $existClientEmployee->salary;
                    $existClientEmployee->fixed_allowance = $row['fixed_allowance'] ?? $existClientEmployee->fixed_allowance;
                    $existClientEmployee->allowance_for_responsibilities = $row['allowance_for_responsibilities'] ?? $existClientEmployee->allowance_for_responsibilities;
                    $data['cron_job'] = 1;
                } else {
                    $data['cron_job'] = 0;
                }

                if (!empty($row['currency']) && $existClientEmployee->currency != $row['currency']) {
                    $existClientEmployee->currency = $row['currency'];
                }

                $existClientEmployee->saveQuietly();

                ClientEmployeeSalaryHistory::create($data);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        }
    }

    public static function updateClientEmployee($row, $clientId, $hasPermission = false)
    {
        $allowLogin = $row['allow_login'];
        $overWrite = $row['overwrite'];
        $exceptData = ['ordinal_number', 'allow_login', 'overwrite', 'username', 'email', 'role'];

        if (!$hasPermission) {
            $exceptData = array_merge($exceptData, [
                "salary",
                "allowance_for_responsibilities",
                "fixed_allowance",
                "salary_for_social_insurance_payment",
            ]);
        }

        $clientEmployeeData = collect($row)
            ->except($exceptData)
            ->mapWithKeys(function ($value, $key) {
                switch ($key) {
                    case 'position':
                        $key = 'client_position_id';
                        break;
                    case 'department':
                        $key = 'client_department_id';
                        break;
                    case in_array($key, self::CLIENT_EMPLOYEE_FORMAT_DATE) && empty($value):
                        $value = self::isValidDateOrDatetime($value);
                        break;
                    case in_array($key, self::CLIENT_EMPLOYEE_INIT_DEFAULT) && empty($value):
                        $value = 0;
                        break;
                    case in_array($key, self::CLIENT_EMPLOYEE_INIT_DATA) && empty($value):
                        $value = "";
                        break;
                    case 'blood_group':
                        if (!empty($value)) {
                            $value = self::BLOOD_GROUPS[$value];
                        }
                        break;
                }
                return [$key => $value];
            })
            ->toArray();

        // BEGIN - Data User
        $fieldBasic = ['client_id', 'code', 'full_name'];
        if ($allowLogin) {
            $fieldBasic = array_merge($fieldBasic, ['username', 'email']);
        }

        $userData = collect($row)
            ->only($fieldBasic)
            ->mapWithKeys(function ($value, $key) {
                return [$key === 'full_name' ? 'name' : $key => $value];
            })
            ->toArray();
        // END - Data User

        // Update
        if ($overWrite) {
            if (empty($userData['username'])) {
                unset($userData['username']);
            } else {
                $userData['username'] = "{$clientId}_{$userData['username']}";
            }
            if (empty($userData['email'])) {
                unset($userData['email']);
            }

            DB::transaction(function () use ($clientEmployeeData, $userData, $clientId, $allowLogin) {
                $clientEmployee = ClientEmployee::where(['client_id' => $clientId, 'code' => $clientEmployeeData['code']])->first();
                if ($clientEmployee->user_id) {
                    $clientEmployee->update($clientEmployeeData);
                    $clientEmployee->user->update($userData);
                } else {
                    if ($allowLogin) {
                        $userData['password'] = bcrypt(Str::random(10));
                        $user = User::create($userData);
                        $clientEmployee->update(array_merge($clientEmployeeData, ['user_id' => $user->id]));
                    } else {
                        $clientEmployee->update($clientEmployeeData);
                    }
                }
            });
        } else {
            // Create
            $userData['password'] = bcrypt(Str::random(10));
            $clientEmployeeData['year_paid_leave_expiry'] = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');
            DB::transaction(function () use ($clientEmployeeData, $userData, $allowLogin) {
                if ($allowLogin) {
                    $user = User::create($userData);
                    $user->clientEmployee()->create($clientEmployeeData);
                } else {
                    ClientEmployee::create($clientEmployeeData);
                }
            });
        }
    }

    public static function updateDependent($row, $clientId)
    {
        $existClientEmployee = ClientEmployee::where(['client_id' => $clientId, 'code' => $row['code']])->first();
        DB::transaction(function () use ($row, $existClientEmployee) {
            ClientEmployeeDependent::updateOrCreate(['client_employee_id' => $existClientEmployee->id, 'tax_code' => $row['tax_code']], $row);
        });
    }

    public static function updatePaidLeave($row, $clientId)
    {
        $existClientEmployee = ClientEmployee::where(['client_id' => $clientId, 'code' => $row['code']])->first();
        DB::transaction(function () use ($row, $existClientEmployee) {
            $nowDate = Carbon::now();
            $effectiveAt = Carbon::parse($row['start_import_paidleave']);
            $startedImportPaidLeave = Carbon::parse($row['start_import_paidleave']);
            $hoursImportPaidleave = $row['hours_import_paidleave'];
            $hours = $existClientEmployee->year_paid_leave_count;
            $type = $row['type'];

            if ($type == 1) {
                // kiểm tra nếu ngày nhập bé hơn ngày hiện tại
                if ($nowDate->format('Y-m-d') >= $startedImportPaidLeave->format('Y-m-d')) {
                    $hours += $hoursImportPaidleave;
                } else {
                    // trường hợp ngày nhập lớn hơn ngày hiện tại thì lùi ngày bắt đầu nhập 1 tháng vi job đang lấy ngày đã import công thêm 1 tháng
                    $startedImportPaidLeave->subMonth(1);
                }
            } elseif ($type == 2) {
                if ($nowDate->format('Y-m-d') >= $startedImportPaidLeave->format('Y-m-d')) {
                    $hours = $hoursImportPaidleave;
                } else {
                    // trường hợp ngày nhập lớn hơn ngày hiện tại thì lùi ngày bắt đầu nhập 1 năm vi job đang lấy ngày đã import công thêm 1 năm
                    $startedImportPaidLeave->subYear(1);
                }
            }

            $dataToUpdate = [
                'start_import_paidleave' => $effectiveAt->format('Y-m-d'),
                'case_import_paidleave' => $type == 1 ? 'tang_hang_thang' : 'tang_hang_nam',
                'hours_import_paidleave' => $row['hours_import_paidleave'],
                'year_paid_leave_count' => $hours,
                'started_import_paidleave' => $startedImportPaidLeave->format('Y-m-d'),
            ];

            if ($nowDate->format('Y-m-d') >= $startedImportPaidLeave->format('Y-m-d') && ($nowDate->isSameDay($effectiveAt) || !$effectiveAt->isAfter($nowDate))) {
                $existClientEmployee->update($dataToUpdate);
                // Update leave hours with year leave type
                $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where('client_employee_id', $existClientEmployee->id)
                    ->whereHas('leaveCategory', function ($query) use ($nowDate) {
                        $query->where('type', 'authorized_leave')
                            ->where('sub_type', 'year_leave')
                            ->where('start_date', '<=', $nowDate)
                            ->where('end_date', '>=', $nowDate);
                    })
                    ->first();

                if ($clientEmployeeLeaveManagement) {
                    $clientEmployeeLeaveManagement->entitlement += ($type == 1) ? $hoursImportPaidleave : $hours;
                    $clientEmployeeLeaveManagement->save();
                }
            } else {
                $existClientEmployee->update(Arr::except($dataToUpdate, ['year_paid_leave_count']));
            }
        });
    }

    public static function updateLeave($row, $clientId, $type)
    {
        $existClientEmployee = ClientEmployee::select("id", "leave_balance")->where(['client_id' => $clientId, 'code' => $row['code']])->first();
        DB::transaction(function () use ($row, $existClientEmployee, $type) {
            $list = LeaveHelper::LEAVE_BALANCES[$type];
            $leaveBalance = json_decode($existClientEmployee->leave_balance, true);
            foreach ($list as $key => $value) {
                $leave = $row[$key];
                if ($leave !== null) {
                    if ($row['action']) {
                        ClientEmployee::where("id", $existClientEmployee->id)->update(["leave_balance->{$type}->$key" => $leave]);
                    } else {
                        ClientEmployee::where("id", $existClientEmployee->id)->update(["leave_balance->{$type}->$key" => $leaveBalance[$type][$key] + $leave]);
                    }
                }
            }
        });
    }

    public static function validateClientEmployee($row)
    {
        return Validator::make($row, [
            'ordinal_number' => ['required'],
            'code' => ['required', $row['overwrite'] ? new UserCodeExistsRule : new UserCodeAvailableRule],
            'full_name' => ['required'],
            'sex' => ['in:female,male', 'nullable'],
            'education_level' => ['in:university,college,intermediate,elementary_occupations,vocational_training_regularly,untrained', 'nullable'],
            'educational_qualification' => ['in:doctorate,master,bachelor,engineer,specialist,professor,other', 'nullable'],
            'position' => ['required', "exists:client_position,id"],
            'department' => ['required', "exists:client_departments,id"],
            'year_paid_leave_count' => ['numeric', 'nullable'],
            'is_insurance_applicable' => ['in:0,1,2,3,4,5,6,7', 'nullable'],
            'spouse_working_at_company' => ['in:0,1', 'nullable'],
            'username' => ['required_if:allow_login,1', 'nullable', 'regex:/^[a-z0-9_.]+$/', new UsernameExistsAvailableRule],
            'email' => ['required_if:allow_login,1', 'nullable', 'email'],
            'allow_login' =>  ['required', 'in:0,1'],
            'overwrite' =>  'required', 'in:0,1'
        ]);
    }

    public static function validateSalaryInformation($row)
    {
        return Validator::make($row, [
            'ordinal_number' => ['required'],
            'code' => ['required', new UserCodeExistsRule],
            'full_name' => ['required'],
            'currency' => ['required', 'in:VND,USD,JPY'],
            'salary' => ['required', 'numeric'],
            'allowance_for_responsibilities' => ['nullable', 'numeric'],
            'fixed_allowance' => ['nullable', 'numeric'],
            'effective_date' => ['required', 'date_format:Y-m-d', new SalaryHistoryRule],
        ]);
    }

    public static function validateDependantInformation($row)
    {
        return Validator::make($row, [
            'ordinal_number' => ['required'],
            'code' => ['required', new UserCodeExistsRule],
            'full_name' => ['required'],
            'name_dependents' => ['required'],
            'tax_code' => ['required'],
            'relationship_code' => ['required', 'in:01,02,03,04'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }

    public static function validateContractInformation($row)
    {
        return Validator::make($row, [
            'ordinal_number' => ['required'],
            'code' => ['required', new UserCodeExistsRule],
            'full_name' => ['required'],
            'status' => ['required', 'in:1,2,3,4'],
            'type_of_employment_contract' => ['required', 'in:1,2,3,4'],
            'contract_no_1' => ['nullable'],
            'probation_start_date' => ['nullable', 'date_format:Y-m-d'],
            'probation_end_date' => ['nullable', 'date_format:Y-m-d', 'after:probation_start_date'],
            'contract_no_2' => ['nullable'],
            'definite_term_contract_first_time_start_date' => ['nullable', 'date_format:Y-m-d'],
            'definite_term_contract_first_time_end_date' => ['nullable', 'date_format:Y-m-d', 'after:definite_term_contract_first_time_start_date'],
            'contract_no_3' => ['nullable'],
            'definite_term_contract_second_time_start_date' => ['nullable', 'date_format:Y-m-d'],
            'definite_term_contract_second_time_end_date' => ['nullable', 'date_format:Y-m-d', 'after:definite_term_contract_second_time_start_date'],
            'contract_no_4' => ['nullable'],
            'indefinite_term_contract_start_date' => ['nullable', 'date_format:Y-m-d'],
            'contract_no_5' => ['nullable'],
            'other_term_contract_start_date' => ['nullable', 'date_format:Y-m-d'],
            'resignation_date' => ['nullable'],
        ]);
    }

    public static function validatePaidLeave($row)
    {
        return Validator::make($row, [
            'ordinal_number' => ['required'],
            'code' => ['required', new UserCodeExistsRule],
            'full_name' => ['required'],
            'start_import_paidleave' => ['required', 'date_format:Y-m-d'],
            'hours_import_paidleave' => ['required', 'numeric'],
            'type' => ['required', 'in:1,2'],
        ]);
    }

    public static function validateLeave($row, $type)
    {
        $rule = [
            'ordinal_number' => ['required'],
            'code' => ['required', new UserCodeExistsRule],
            'full_name' => ['required'],
            'action' => ['required', 'in:0,1'],
        ];

        if ($type === self::AUTHORIZED_LEAVE) {
            $rule = array_merge($rule, ['self_marriage_leave' => [new LeaveRule]]);
        } else {
            $rule = array_merge($rule, ['pregnant_leave' => [new LeaveRule]]);
        }

        return Validator::make(array_merge($row, ['type' => $type]), $rule);
    }

    public static function getClientEmployeeData($clientId, $code, $column)
    {
        return ClientEmployee::select($column)
            ->where(['client_id' => $clientId, 'code' => $code])
            ->first();
    }

    public static function getUpdatedValue($header, $clientEmployee)
    {
        switch ($header) {
            case 'old_salary':
                return $clientEmployee->salary;
            case 'old_allowance_for_responsibilities':
                return $clientEmployee->allowance_for_responsibilities;
            case 'old_fixed_allowance':
                return $clientEmployee->fixed_allowance;
            default:
                return null;
        }
    }
}
