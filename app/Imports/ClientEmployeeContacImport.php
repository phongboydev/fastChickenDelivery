<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Exports\ClientEmployeeContactFromTemplate;
use Illuminate\Http\File;

class ClientEmployeeContacImport implements ToCollection
{

    protected $clientId;
    protected $templateExport;
    protected $pathFile;
    protected $groupIds;

    public function __construct(string $clientId, string $templateExport, string $pathFile, $groupIds = [])
    {
        $this->clientId = $clientId;
        $this->templateExport = $templateExport;
        $this->pathFile = $pathFile;
        $this->groupIds = $groupIds;

        return $this;
    }

    public function collection(Collection $rows)
    {

        $templateVariable = $this->getTemplateVariables($rows);

        Excel::store((new ClientEmployeeContactFromTemplate(
            $this->clientId,
            $this->templateExport,
            $templateVariable,
            $rows,
            $this->groupIds
        )), $this->pathFile, 'minio');

    }

    protected function getTemplateVariables( $rows )
    {

        $templateVariable = [
            '$LOOP_START' => [],
            '$NO' => [],
            '$CODE' => [],
            '$NAME' => [],
            '$S_DATE_OF_BIRTH' => [], 
            '$S_GENDER' => [], 
            '$S_CONTRACT_NO_PROBATION' => [], 
            '$S_PROBATION_START_DATE' => [], 
            '$S_PROBATION_END_DATE' => [], 
            '$S_CONTRACT_NO_DEFINITE_TERM_CONTRACT_FIRST_TIME' => [], 
            '$S_DEFINITE_TERM_CONTRACT_FIRST_TIME_START_DATE' => [], 
            '$S_DEFINITE_TERM_CONTRACT_FIRST_TIME_END_DATE' => [], 
            '$S_CONTRACT_NO_DEFINITE_TERM_CONTRACT_SECOND_TIME' => [], 
            '$S_DEFINITE_TERM_CONTRACT_SECOND_TIME_START_DATE' => [], 
            '$S_DEFINITE_TERM_CONTRACT_SECOND_TIME_END_DATE' => [], 
            '$S_CONTRACT_NO_INDEFINITE_TERM_CONTRACT' => [], 
            '$S_INDEFINITE_TERM_CONTRACT_START_DATE' => [], 
            '$S_RESIGNATION_DATE' => [],
            '$LOOP_END' => [],
        ];

        foreach ($rows as $rowIndex => $row) {
    
            foreach ($row as $key => $value) {

                foreach( $templateVariable as $d => $v ) {
                   
                    if( $value === $d ) {

                        $templateVariable[$d][] = [$rowIndex, $key];
                        
                    } 
                }
            }
        }

        return $templateVariable;
    }
}