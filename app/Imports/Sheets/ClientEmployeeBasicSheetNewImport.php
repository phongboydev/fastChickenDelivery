<?php

namespace App\Imports\Sheets;

use App\Exceptions\CustomException;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeContract;
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

class ClientEmployeeBasicSheetNewImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;

    protected const RIGHT_HEADER = [
        "code" => ['code_employee_not_exists', 'required'],
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

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    public function collection(Collection $rows)
    {
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
        return self::RIGHT_HEADER;
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

    public function transformContractType($value)
    {
        switch ($value) {
            case 1:
                return "thuviec";
                break;
            case 2:
                return "co-thoi-han-lan-1";
                break;
            case 3:
                return "co-thoi-han-lan-2";
                break;
            case 4:
                return "khong-xac-dinh-thoi-han";
                break;
            default:
                return "khac";
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
