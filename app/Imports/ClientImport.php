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
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

use App\Models\Client;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ClientImport implements WithMappedCells, ToModel, WithMultipleSheets
{
    use Importable;

    public $client;

    public function sheets()
    : array
    {
        return [ 0 => $this ];
    }

    const DATE_CELLS = ['company_license_issued_at', 'company_license_updated_at'];

    const VALIDATION_RULES = [
        '*.company_name'                                    => 'required',
        '*.code'                                            => 'required|unique:clients',
        '*.address'                                         => 'required',
        '*.type_of_business'                                => 'required',
        '*.company_account_number'                          => 'nullable',
        '*.company_bank_name'                               => 'nullable',
        '*.company_contact_phone'                           => 'nullable',
        '*.company_contact_email'                           => 'nullable',
        '*.company_bank_branch'                             => 'nullable',
        '*.person_signing_a_bank_document'                  => 'nullable',
        '*.social_insurance_agency'                         => 'nullable',
        '*.social_insurance_account_name'                   => 'nullable',
        '*.social_insurance_account_number'                 => 'nullable',
        '*.social_insurance_bank_name'                      => 'nullable',
        '*.social_insurance_bank_branch'                    => 'nullable',
        '*.trade_union_agency'                              => 'nullable',
        '*.trade_union_account_name'                        => 'nullable',
        '*.trade_union_account_number'                      => 'nullable',
        '*.trade_union_bank_name'                           => 'nullable',
        '*.trade_union_bank_branch'                         => 'nullable',

        '*.rewards_for_achievements'                        => 'nullable|integer|min:0|max:1',
        '*.annual_salary_bonus'                             => 'nullable|integer|min:0|max:1',

        '*.employees_number_foreign'                        => 'required|integer|min:0',
        '*.employees_number_vietnamese'                     => 'required|integer|min:0',
        '*.timesheet_min_time_block'                        => 'nullable',

        '*.presenter_name'                                  => 'nullable|string',
        '*.presenter_email'                                 => 'nullable|email',
        '*.presenter_phone'                                 => 'nullable',
        '*.company_contact_fax'                             => 'nullable',

        '*.company_license_at'                              => 'nullable',
        '*.company_license_no'                              => 'nullable',
        '*.company_license_issuer'                          => 'nullable',
        '*.company_license_issued_at'                       => 'nullable|date_format:d/m/Y',
        '*.company_license_updated_at'                      => 'nullable|date_format:d/m/Y',
    ];

    public function mapping(): array
    {
        return [
            'company_name'  => 'D10',
            'company_contact_phone' => 'D62',
            'company_contact_email' => 'G62',
            'code' => 'G10',
            'presenter_name' => 'D12',
            'presenter_email' => 'G12',
            'presenter_phone' => 'D14',
            'company_contact_fax' => 'G14',
            'address' => 'D16',
            'company_license_at' => 'G16',
            'company_license_no' => 'D18',
            'company_license_issuer' => 'G18',
            'company_license_issued_at' => 'D20',
            'company_license_updated_at' => 'G20',
            'company_account_number' => 'D24',
            'company_bank_name' => 'G24',
            'company_bank_branch' => 'D26',
            'person_signing_a_bank_document' => 'G26',
            'annual_salary_bonus' => 'D30',
            'rewards_for_achievements' => 'G30',
            'social_insurance_agency' => 'D35',
            'social_insurance_account_name' => 'G35',
            'social_insurance_account_number' => 'D37',
            'social_insurance_bank_name' => 'G37',
            'social_insurance_bank_branch' => 'D39',
            // 'social_insurance_and_health_insurance_ceiling' => 'G38',
            // 'unemployment_insurance_ceiling' => 'D40',
            'trade_union_agency' => 'D44',
            'trade_union_account_name' => 'G44',
            'trade_union_account_number' => 'D46',
            'trade_union_bank_name' => 'G46',
            'trade_union_bank_branch' => 'D48',
            'employees_number_foreign' => 'D52',
            'employees_number_vietnamese' => 'G52',
            'timesheet_min_time_block' => 'D56',
            'type_of_business' => 'D58'
        ];
    }

    public function model(array $row)
    {
        foreach(self::DATE_CELLS as $dateCell){
            if(isset($row[$dateCell])){
                $row[$dateCell] = $this->transformDate($row[$dateCell]);
            }
        }

        $timesheet_min_time_block = explode(' ', $row['timesheet_min_time_block']);
        // set default time block is 1
        if(!empty($timesheet_min_time_block[0])  && intval($timesheet_min_time_block[0]) > 0) {
            $row['timesheet_min_time_block'] = $row['ot_min_time_block'] = intval($timesheet_min_time_block[0]);
        } else {
            $row['timesheet_min_time_block'] = $row['ot_min_time_block'] = 1;
        }

        $client = new Client($row);

        $this->client = $client;

        return $client;
    }

    public function rules()
    : array
    {
        return self::VALIDATION_RULES;
    }

    /**
     * @return int
     */
    public function startRow()
    : int
    {
        return 3;
    }

    /**
     * Transform a date value into a Carbon object.
     *
     * @return Carbon|null
     */
    public function transformDate($value, $format = 'Y-m-d')
    {
        if($value === null) return null;

        try {

            if ((is_int((int)$value)) && (strlen((string)$value) == 5)) {
                $date = new \DateTime(Carbon::instance(Date::excelToDateTimeObject($value)));

                return $date->format($format);
            }
            return false;

        } catch (ErrorException $e) {
            return false;
        }
    }

    public function validate($sheet) {

        $columns = $this->mapping();

        $rows = $sheet->toArray(null, true, false);

        $errors = [
            'formats' => [],
            'startRow' => 0
        ];

        foreach($columns as $colName => $colPos) {

            $colIndexs = Coordinate::coordinateFromString($colPos);

            $colIndex = Coordinate::columnIndexFromString($colIndexs[0]);
            $rowIndex = $colIndexs[1];

            $cellData = $rows[($rowIndex - 1)][($colIndex - 1)];

            if(in_array($colName, self::DATE_CELLS)){
                $cellData = $this->transformDate($cellData, 'd/m/Y');
            }

            if(isset(self::VALIDATION_RULES['*.' . $colName])){
                $validator = Validator::make([$colName => $cellData], [
                    $colName => self::VALIDATION_RULES['*.' . $colName]
                ]);

                if ($validator->fails()) {
                    $errorsMsg = $validator->errors()->toArray();

                    $errors['formats'][] = ['row' => $rowIndex, 'col' => $colIndex, 'name' => $colName, 'error' => $errorsMsg[$colName]];
                }
            }
        }

        return $errors;
    }

}
