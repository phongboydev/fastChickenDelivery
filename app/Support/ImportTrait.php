<?php

namespace App\Support;

use App\Models\ClientEmployee;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;

use App\User;
use Carbon\Carbon;
use Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;
USE App\Support\Constant;

trait ImportTrait
{
    abstract public function getRightHeader();
    abstract public function startRow();
    abstract public function endRow();
    abstract public function totalCol();
    abstract public function getClientID();
    abstract public function startHeader();
    abstract public function isDynamicsStartRow();

    public function getData($sheet)
    {
        $rows = $sheet->toArray(null, true, false);

        $header = [];

        $startRow = $this->isDynamicsStartRow() ? $this->startRow($rows) : $this->startRow();
        $endRow = $this->endRow($rows);
        $startHeader = $this->isDynamicsStartRow() ? $this->startHeader($rows) : $this->startHeader();

        for ($i = 1; $i < $startRow; $i++) {
            if ($i == $startHeader) {
                $header = array_shift($rows);

                $header = array_slice($header, 0, $this->totalCol());

                foreach ($header as $index => &$h) {
                    if (is_null($h) || $h == '') {
                        $h = 'No.' . $index;
                    }
                }
            } else {
                array_shift($rows);
            }
        }

        $filteredData = collect([]);

        foreach ($rows as $index => $r) {

            if ($endRow == -1 || $index < $endRow) {
                $row = array_slice($r, 0, $this->totalCol());

                $allColsIsEmpty = empty(array_filter($row, function ($v) {
                    return !empty($v);
                }));
                if (!$allColsIsEmpty) {

                    $filteredData->push($row);
                }
            }
        }

        if ($filteredData->isNotEmpty()) {
            return [
                'header' => $header,
                'rows' => $filteredData->all()
            ];
        } else {
            return [];
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

    public function isDate($value, $format = 'Y-m-d')
    {

        if (is_numeric($value) && (strlen((string)$value) == 4)) {
            $value = (string)$value . '-' . Constant::DEFAULT_APPEND_TO_DATE;
            return Carbon::createFromFormat($format, $value);
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

    public function hasEmployee($code)
    {
        $employee = ClientEmployee::where('client_id', $this->getClientID())->where('code', $code)->first();

        return !empty($employee);
    }

    public function checkUsername($row)
    {
        $clientId = $this->getClientID();

        $user = User::where('client_id', $this->getClientID())->where('username', $clientId . '_' . $row['username'])->first();

        if (!empty($user)) {
            $employee = ClientEmployee::where('client_id', $this->getClientID())->where('code', $row['code'])->first();

            return !empty($employee);
        }

        return true;
    }

    public function buildAddress($address)
    {
        $result = [
            'address' => isset($address['address']) ? $address['address'] : '',
            'full_address' => '',
            'province_id' => '',
            'district_id' => '',
            'ward_id' => ''
        ];

        $fullAddress = [];

        if(isset($address['province'])) {
            $address['province'] = trim(explode('(',trim($address['province']))[0]);
            $item = Province::select('*')->where('province_name', 'LIKE', $address['province'])->first();
            if($item) {
                $fullAddress[] = $item->province_name;
                $result['province_id'] = $item->id;
            }
        }

        if(isset($address['district']) && isset($address['province'])) {
            $address['district'] = trim(explode('(',trim($address['district']))[0]);
            $item = ProvinceDistrict::select('*')
                ->where('province_id', $result['province_id'])
                ->where('district_name', 'LIKE', $address['district'])->first();
            if($item) {
                $fullAddress[] = $item->district_name;
                $result['district_id'] = $item->id;
            }
        }

        if(isset($address['ward']) && isset($address['district']) && isset($address['province'])) {
            $address['ward'] = trim(explode('(',trim($address['ward']))[0]);
            $item = ProvinceWard::select('*')
                ->where('province_id', $result['province_id'])
                ->where('province_district_id', $result['district_id'])
                ->where('ward_name', 'LIKE', $address['ward'])->first();
            if($item) {
                $fullAddress[] = $item->ward_name;
                $result['ward_id'] = $item->id;
            }
        }

        if( $result['address'] ) $fullAddress[] = $result['address'];

        if($fullAddress)
            $result['full_address'] = implode(', ', array_reverse($fullAddress));

        return $result;
    }

    public function validateExitAddress(&$result, $value, $type) {
        $value = trim(explode('(',trim($value))[0]);
        if(!isset($result['province_id']) && $type == 'province_exits') {
            $item = Province::select('*')->where('province_name', 'LIKE', $value)->first();
            if($item) {
                $result['province_id'] = $item->id;
            }
        }
        if(!empty($result['province_id']) && !isset($result['district_id']) && $type == 'district_exits') {
            $item = ProvinceDistrict::select('*')
                ->where('province_id', $result['province_id'])
                ->where('district_name', 'LIKE', $value)->first();
            if ($item) {
                $result['district_id'] = $item->id;
            }
        }

        if(!empty($result['province_id']) && !empty($result['district_id']) && !isset($result['ward_id']) && $type == 'ward_exits') {
             $item = ProvinceWard::select('*')
                ->where('province_id', $result['province_id'])
                ->where('province_district_id', $result['district_id'])
                ->where('ward_name', 'LIKE', $value)->first();
            if ($item) {
                $result['ward_id'] = $item->id;
            }
        }
    }
}
