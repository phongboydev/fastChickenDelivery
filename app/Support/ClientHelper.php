<?php

namespace App\Support;

use App\Models\ErrorLog;
use Carbon\Carbon;
use Browser;
use App\Models\Formula;
use App\Models\ClientPayrollHeadCount;
use App\Models\ClientEmployeePayrollHeadCount;
use App\Models\ClientWorkflowSetting;
use App\Models\ClientEmployee;
use Illuminate\Support\Facades\DB;

class ClientHelper
{
    public static function getClientEmployeeLimit($client_id)
    {
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $client_id)->first();

        if ($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $clientWorkflowSetting->client_employee_limit) {
            return $clientWorkflowSetting->client_employee_limit;
        }
        return 0;
    }

    public static function validateLimitEmployee($client_id)
    {
        $LIMIT = self::getClientEmployeeLimit($client_id);

        $now = Carbon::now();

        $clientEmployeePayrollHeadCounts = ClientEmployeePayrollHeadCount::where('client_id', $client_id)
            ->whereHas('clientEmployee', function ($query) {
                $query->whereNotNull('user_id')->where('status', '!=', 'nghỉ việc');
            })
            ->where('month', $now->format('m'))
            ->where('year', $now->format('Y'))
            ->distinct('client_employee_id')->get();

        $total = $clientEmployeePayrollHeadCounts->count();

        logger('@clientEmployeePayrollHeadCounts', [$clientEmployeePayrollHeadCounts]);
        logger('@ValidateLimitEmployee - ' . $client_id, [$LIMIT, $total, $now->format('m'), $now->format('Y')]);

        return $total < $LIMIT;
    }

    public static function validateLimitActivatedEmployee($client_id)
    {
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $client_id)->first();
        //we always return true if internal disabled "automatic salary calculation" feature for clients.
        if (!$clientWorkflowSetting->enable_create_payroll) {
            return true;
        }

        $LIMIT = self::getClientEmployeeLimit($client_id);

        $total = self::getTotalActivatedClientEmployee($client_id);

        return $total < $LIMIT;
    }

    public static function validateLimitActivatedEmployeeWithNewEmployeeNumber($client_id, $newEmployeeNumber)
    {
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $client_id)->first();
        //we always return true if internal disabled "automatic salary calculation" feature for clients.
        if (!$clientWorkflowSetting->enable_create_payroll) {
            return true;
        }

        $LIMIT = self::getClientEmployeeLimit($client_id);

        $total = self::getTotalActivatedClientEmployee($client_id);

        return ($total + $newEmployeeNumber) <= $LIMIT;
    }

    public static function validatePayrollHeadcount($client_id, $month, $year, $employees)
    {
        $LIMIT = self::getClientEmployeeLimit($client_id);

        $clientHeadCount = ClientPayrollHeadCount::where('client_id', $client_id)
            ->where('month', $month)
            ->where('year', $year)
            ->count();

        if ($clientHeadCount > $LIMIT) {
            return false;
        }

        $clientEmployeePayrollHeadCounts = ClientEmployeePayrollHeadCount::select('*')
            ->where('client_id', $client_id)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        if ($clientEmployeePayrollHeadCounts->isNotEmpty()) {

            $headCountEmployees = $clientEmployeePayrollHeadCounts->pluck('client_employee_id');

            $finalEmployees = array_unique(array_merge($employees, $headCountEmployees->all()));

            return count($finalEmployees) <= $LIMIT;
        } else {
            return true;
        }
    }

    public static function validateHeadcountChange($client_id, $headcount_change)
    {
        $total = self::getTotalActivatedClientEmployee($client_id);

        return $total <= $headcount_change;
    }

    public static function updatePayrollHeadcount($client_id, $month, $year, $employees)
    {
        foreach ($employees as $employeeID) {

            $clientEmployeeHeadCountData = [
                'client_id' => $client_id,
                'client_employee_id' => $employeeID,
                'month' => $month,
                'year' => $year,
            ];

            ClientEmployeePayrollHeadCount::updateOrCreate($clientEmployeeHeadCountData, $clientEmployeeHeadCountData);
        }

        $total = ClientEmployeePayrollHeadCount::where('client_id', $client_id)
            ->where('month', $month)
            ->where('year', $year)->count();

        ClientPayrollHeadCount::updateOrCreate([
            'client_id' => $client_id,
            'month' => $month,
            'year' => $year,
        ], [
            'client_id' => $client_id,
            'month' => $month,
            'year' => $year,
            'total' => $total
        ]);
    }

    public static function getValidRootFormula()
    {
        $today = Carbon::today()->format('Y-m-d');

        $query = Formula::whereNull('client_id')
            ->whereNull('parent_id')
            ->whereDate('begin_effective_at', '<=', $today)
            ->whereDate('end_effective_at', '>=', $today)
            ->whereNotNull('end_effective_at')
            ->orWhere(function ($q) {
                return $q->whereNull('begin_effective_at')
                    ->whereNull('end_effective_at')
                    ->whereNull('parent_id')
                    ->whereNull('client_id');
            })
            ->orWhere(function ($q) use ($today) {
                return $q->whereDate('begin_effective_at', '<=', $today)
                    ->whereNull('end_effective_at')
                    ->whereNull('parent_id')
                    ->whereNull('client_id');
            });

        $rootFormulas = $query->get();

        $query = Formula::whereNull('client_id')
            ->whereNotNull('parent_id')
            ->whereDate('begin_effective_at', '<=', $today)
            ->whereDate('end_effective_at', '>=', $today)
            ->whereNotNull('end_effective_at');

        $query->orWhere(function ($q) use ($today) {

            return $q->whereNull('client_id')
                ->whereNotNull('parent_id')
                ->whereDate('begin_effective_at', '<=', $today)
                ->whereNull('end_effective_at');
        });

        $childFormulas = $query->get();

        if ($rootFormulas) {

            if (!$childFormulas) return $rootFormulas;

            foreach ($childFormulas as $f) {

                $hasSameFunc = $rootFormulas->where('func_name', $f->func_name)->all();

                if (!$hasSameFunc) {
                    $rootFormulas->push($f);
                }
            }

            return $rootFormulas;
        } else {

            return $childFormulas;
        }
    }

    public static function getValidClientFormula($clientId)
    {
        $today = Carbon::today()->format('Y-m-d');

        $query = Formula::where('client_id', $clientId)
            ->whereNull('parent_id')
            ->whereDate('begin_effective_at', '<=', $today)
            ->whereDate('end_effective_at', '>=', $today)
            ->whereNotNull('end_effective_at')
            ->orWhere(function ($q) use ($clientId) {
                return $q->whereNull('begin_effective_at')
                    ->whereNull('end_effective_at')
                    ->whereNull('parent_id')
                    ->whereNull('client_id');
            })
            ->orWhere(function ($q) use ($today) {
                return $q->whereDate('begin_effective_at', '<=', $today)
                    ->whereNull('end_effective_at')
                    ->whereNull('parent_id')
                    ->whereNull('client_id');
            });

        $rootFormulas = $query->get();

        $query = Formula::where('client_id', $clientId)
            ->whereNotNull('parent_id')
            ->whereDate('begin_effective_at', '<=', $today)
            ->whereDate('end_effective_at', '>=', $today)
            ->whereNotNull('end_effective_at');

        $query->orWhere(function ($q) use ($today, $clientId) {

            return $q->where('client_id', $clientId)
                ->whereNotNull('parent_id')
                ->whereDate('begin_effective_at', '<=', $today)
                ->whereNull('end_effective_at');
        });

        $childFormulas = $query->get();

        if ($rootFormulas) {

            if (!$childFormulas) return $rootFormulas;

            foreach ($childFormulas as $f) {

                $hasSameFunc = $rootFormulas->where('func_name', $f->func_name)->all();

                if (!$hasSameFunc) {
                    $rootFormulas->push($f);
                }
            }

            return $rootFormulas;
        } else {

            return $childFormulas;
        }
    }

    public static function getValidatedFormulas($clientId = '')
    {
        $rootFormulas = self::getValidRootFormula();

        $clientFormulas = self::getValidClientFormula($clientId);

        if ($rootFormulas) {

            foreach ($clientFormulas as $f) {

                $hasSameFunc = $rootFormulas->where('func_name', $f->func_name)->all();

                if ($hasSameFunc) {
                    $rootFormulas = $rootFormulas->reject(function ($value) use ($f) {
                        return $value->func_name == $f->func_name;
                    });
                }

                $rootFormulas->push($f);
            }

            return $rootFormulas->values();
        } else {
            return $clientFormulas->values();
        }
    }

    public static function checkConditionSettingCompare($value, $valueNeedCompare, $type)
    {
        $value = floatval($value);
        $valueNeedCompare = floatval($valueNeedCompare);
        $isTrue = false;
        switch ($type) {
            case Constant::COMPARISON_OPERATOR[0]:
                if ($value > $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            case Constant::COMPARISON_OPERATOR[1]:
                if ($value >= $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            case Constant::COMPARISON_OPERATOR[2]:
                if ($value < $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            case Constant::COMPARISON_OPERATOR[3]:
                if ($value <= $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            case Constant::COMPARISON_OPERATOR[4]:
                if ($value == $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            case Constant::COMPARISON_OPERATOR[5]:
                if ($value != $valueNeedCompare) {
                    $isTrue = true;
                }
                break;
            default:
                break;
        }
        return $isTrue;
    }

    public static function getTotalActivatedClientEmployee($client_id)
    {
        return ClientEmployee::where('client_id', '=', $client_id)->status()->count();
    }

    public static function countClientEmployeeByStatus($client_id)
    {
        return ClientEmployee::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->where('client_id', '=', $client_id)
            ->get();
    }

    public static function getInfoApp($isJSON = true)
    {
        if (\App::runningInConsole()) return $isJSON ? json_encode([]) : null;

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $browser = Browser::parse($userAgent);

        $infoApp = [
            "os" => $browser->platformFamily(),
            "os_version" => $browser->platformVersion(),
            "model" => "",
            "manufacturer" => "",
            "is_mobile" => $browser->isMobile(),
            "browser" => $browser->browserFamily()
        ];

        // 'Terra Mobile (Android 13; Samsung; SM-S901B) Terra/1.3.10';
        if (strpos($userAgent, "Terra Mobile") !== false) {
            $parseUserAgent = explode('/', $userAgent);
            $version = array_pop($parseUserAgent);

            $infoApp = [
                "os" => $browser->platformFamily(), // Android
                "os_version" => $browser->platformVersion(),
                "model" => $browser->deviceModel(),
                "manufacturer" => $browser->deviceFamily(),
                "is_mobile" => true,
                "browser" => 'Terra ' . $version,
            ];
        }

        return $isJSON ? json_encode($infoApp) : $infoApp;
    }

    static public function logError($underlyingException, $user = null): void
    {
        $log_data = json_encode([
            'file' => $underlyingException->getFile(),
            'line' => $underlyingException->getLine(),
            'code' => $underlyingException->getCode(),
            'trace' => $underlyingException->getTraceAsString(),
        ]);

        $app_info = !$user ? json_encode(['is_job' => 1]) : self::getInfoApp();

        ErrorLog::create([
            'client_id' => !empty($user->client_id) ? $user->client_id : '',
            'user_id' => !empty($user->id) ? $user->id : '',
            'exception_class' => get_class($underlyingException),
            'message' => $underlyingException->getMessage(),
            'log_data' => $log_data,
            'app_info' => $app_info,
            //            'app_info' => json_encode([
            //                'ip_address' => request()->ip(),
            //                'user_agent' => request()->header('user-agent'),
            //            ]),
        ]);
    }

    const CONTRACT_CLIENT_EMPLOYEE_FIELDS = ['FULL_NAME', 'CODE', 'PROBATION_START_DATE', 'PROBATION_END_DATE', 'OFFICIAL_CONTRACT_SIGNING_DATE', 'TYPE_OF_EMPLOYMENT_CONTRACT', 'SALARY', 'ALLOWANCE_FOR_RESPONSIBILITIES', 'FIXED_ALLOWANCE', 'IS_TAX_APPLICABLE', 'IS_INSURANCE_APPLICABLE', 'NUMBER_OF_DEPENDENTS', 'BANK_ACCOUNT', 'BANK_ACCOUNT_NUMBER', 'BANK_NAME', 'BANK_BRANCH', 'SOCIAL_INSURANCE_NUMBER', 'DATE_OF_BIRTH', 'SEX', 'DEPARTMENT', 'POSITION', 'TITLE', 'WORKPLACE', 'MARITAL_STATUS', 'SALARY_FOR_SOCIAL_INSURANCE_PAYMENT', 'EFFECTIVE_DATE_OF_SOCIAL_INSURANCE', 'MEDICAL_CARE_HOSPITAL_NAME', 'MEDICAL_CARE_HOSPITAL_CODE', 'NATIONALITY', 'NATION', 'ID_CARD_NUMBER', 'IS_CARD_ISSUE_DATE', 'ID_CARD_ISSUE_PLACE', 'BIRTH_PLACE_ADDRESS', 'BIRTH_PLACE_STREET', 'BIRTH_PLACE_WARDS', 'BIRTH_PLACE_DISTRICT', 'BIRTH_PLACE_CITY_PROVINCE', 'RESIDENT_ADDRESS', 'RESIDENT_STREET', 'RESIDENT_WARDS', 'RESIDENT_DISTRICT', 'RESIDENT_CITY_PROVINCE', 'CONTACT_ADDRESS', 'CONTACT_STREET', 'CONTACT_WARDS', 'CONTACT_DISTRICT', 'CONTACT_CITY_PROVINCE', 'CONTACT_PHONE_NUMBER', 'HOUSEHOLD_HEAD_INFO', 'HOUSEHOLD_CODE', 'HOUSEHOLD_HEAD_FULLNAME', 'HOUSEHOLD_HEAD_ID_CARD_NUMBER', 'HOUSEHOLD_HEAD_DATE_OF_BIRTH', 'HOUSEHOLD_HEAD_RELATION', 'HOUSEHOLD_HEAD_PHONE', 'RESIDENT_RECORD_NUMBER', 'RESIDENT_RECORD_TYPE', 'RESIDENT_VILLAGE', 'RESIDENT_COMMUNE_WARD_DISTRICT_PROVINCE', 'STATUS', 'QUITTED_AT', 'CREATED_AT', 'UPDATED_AT', 'DELETED_AT', 'ROLE', 'FOREIGNER_JOB_POSITION', 'FOREIGNER_CONTRACT_STATUS', 'EDUCATION_LEVEL', 'MST_CODE'];
}
