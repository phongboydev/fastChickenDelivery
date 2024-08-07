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
use App\Models\ClientEmployeeCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientCustomVariable;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;

class SetupCalculationSheetVariableImport implements ToCollection, WithHeadingRow
{
    use Importable, ImportTrait;

    protected $client_id = null;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
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
        return true;
    }

    /**
     * @return int
     */
    public function startRow($rows): int
    {
        $startRow = 0;

        foreach ($rows as $index => $row) {
            if (in_array('$Start', $row)) {
                $startRow = $index + 3;
            }
        }

        return $startRow;
    }

    public function endRow($rows): int
    {
        $endRow = -1;

        foreach ($rows as $index => $row) {
            if (in_array('$End', $row)) {
                $endRow = $index + 1;
            }
        }
        return $endRow;
    }

    public function startHeader($rows)
    {
        $startHeader = 0;

        foreach ($rows as $index => $row) {
            if (in_array('$Start', $row)) {
                $startHeader = $index + 2;
            }
        }
        return $startHeader;
    }

    public function totalCol()
    {
        return 5;
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->client_id;
    }

    public function getProcessedData($sheet)
    {

        $allData = $this->getData($sheet);

        $mainData = $allData['rows'];

        $processedData = [];

        usort($mainData, function ($a, $b) {
            return strlen($b[1]) - strlen($a[1]);
        });

        foreach ($mainData as $data) {

            $type = $data[4];
            $code = $data[1];
            $readableName = $data[3];
            $variableName = $data[2];

            if ($code) {
                $processedData[$code] = [
                    'code' => $code,
                    'readable_name' => $readableName,
                    'variable_name' => $variableName,
                    'type' => $type
                ];
            }
        }

        return $processedData;
    }

    public function saveProcessedData($data)
    {
        foreach ($data as $v) {

            $variableName = $v['variable_name'];
            $readableName = $v['readable_name'];
            $type = $v['type'];

            $clientCustomVariable = ClientCustomVariable::where('client_id', $this->client_id)->where('variable_name', $variableName)->first();
            switch ($type) {
                case 'Toàn công ty':
                    if (!$clientCustomVariable) {
                        ClientCustomVariable::create([
                            'client_id' => $this->client_id,
                            'scope' => 'client',
                            'readable_name' => $readableName,
                            'variable_name' => $variableName,
                            'variable_value' => 0
                        ]);
                    }else{
                        if($clientCustomVariable->scope == 'client'){
                            ClientCustomVariable::where('id', $clientCustomVariable->id)->update([
                                'readable_name' => $readableName
                            ]);
                        } else {
                            throw new CustomException(
                                __("trung_ten_bien") ." ". $variableName,
                                'HttpException'
                            );
                        }
                    }

                    break;
                case 'Từng nhân viên':
                    if (!$clientCustomVariable) {
                        ClientCustomVariable::create([
                            'client_id' => $this->client_id,
                            'scope' => 'employee',
                            'readable_name' => $readableName,
                            'variable_name' => $variableName,
                            'variable_value' => 0
                        ]);

                        $allEmployees = ClientEmployee::where('client_id', $this->client_id)->get();

                        if ($allEmployees->isNotEmpty()) {

                            foreach ($allEmployees as $employee) {
                                $saveData = [
                                    'client_id' => $this->client_id,
                                    'client_employee_id' => $employee->id,
                                    'readable_name' => $readableName,
                                    'variable_name' => $variableName,
                                    'variable_value' => 0
                                ];

                                ClientEmployeeCustomVariable::create($saveData);
                            }
                        }
                    }else{
                        if($clientCustomVariable->scope == 'employee'){
                            ClientCustomVariable::where('id', $clientCustomVariable->id)->update([
                                'readable_name' => $readableName
                            ]);
                        } else {
                            throw new CustomException(
                                __("trung_ten_bien") ." ". $variableName,
                                'HttpException'
                            );
                        }

                        $client_id = $this->client_id;

                        ClientEmployeeCustomVariable::whereHas('client', function($query) use($client_id) {
                            $query->where('clients.id', $client_id);
                        })
                            ->where('variable_name', $variableName)
                            ->update([
                                'readable_name' => $readableName
                            ]);
                    }

                    break;
            }
        }
    }
}
