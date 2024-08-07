<?php

namespace App\Imports\Sheets;

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

class ClientEmployeeSiPiSheetImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;

    protected $client_id = null;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
        "salary_for_social_insurance_payment" =>  ['number', 'required'],
        "is_insurance_applicable" =>  ['number', 'required'],
        "social_insurance_number" =>  ['string'],
        "medical_care_hospital_name" =>  ['string'],
        "medical_care_hospital_code" =>  ['string'],
        "number_of_dependents" =>  ['number', 'required'],
        "is_tax_applicable" =>  ['number', 'required'],
        "mst_code" =>  ['string'],
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

        foreach ($filteredData as $row) {

            $data = [];

            $clientEmployee = ClientEmployee::where('client_id', '=', $this->client_id)
                ->where('code', '=', $row['code'])
                ->first();

            if (!empty($clientEmployee)) {

                $data['salary_for_social_insurance_payment'] = $row['salary_for_social_insurance_payment'];
                $data['is_insurance_applicable'] = $row['is_insurance_applicable'];
                $data['number_of_dependents'] = $row['number_of_dependents'];
                $data['is_tax_applicable'] = $row['is_tax_applicable'];
                $data['mst_code'] = $row['mst_code'];

                if (isset($row['social_insurance_number']))
                    $data['social_insurance_number'] = $row['social_insurance_number'];

                if (isset($row['medical_care_hospital_name']))
                    $data['medical_care_hospital_name'] = $row['medical_care_hospital_name'];

                if (isset($row['medical_care_hospital_code']))
                    $data['medical_care_hospital_code'] = $row['medical_care_hospital_code'];

                $clientEmployee->update($data);
            } else {
                $errors[] = $row['code'];
            }
        }

        if ($errors) {
            DB::rollBack();

            $mesg = json_encode(['type' => 'validate', 'msg' => 'SI & PIT Information: Can not find these employees: ' . implode(', ', $errors)]);

            throw new CustomException(
                $mesg,
                'VALIDATION_RULES'
            );
        }

        DB::commit();
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->client_id;
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
        return 11;
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
