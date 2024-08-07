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
use App\Models\CalculationSheetTemplate;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use App\Support\Constant;
use Illuminate\Support\MessageBag;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;

class SetupCalculationSheetColumnImport implements ToCollection, WithHeadingRow
{
    use Importable, ImportTrait;

    protected $client_id = null;
    protected $variables = [];
    protected $headers = [];
    protected $filteredData = [];
    protected $maxColumns = 0;

    protected const RIGHT_HEADER = [
        "code" => ['string', 'required'],
        "full_name" => ['string', 'required'],
    ];

    protected $EXCLUDE_REPLACE_VARIABLES = [
        'IF' => '{@}',
        'or' => '{@@}',
        'and' => '{@@@}'
    ];

    function __construct($maxColumns, $clientId, $variableData)
    {
        $this->client_id = $clientId;
        $this->variables = $variableData;
        $this->maxColumns = $maxColumns;
    }

    public function collection(Collection $rows)
    {
        logger("SetupCalculationSheetColumnImport::collection BEGIN");
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

        $this->filteredData = $filteredData;
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
        return -1;
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
        return $this->maxColumns;
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

        $this->headers = $allData['header'];

        $mainData = $allData['rows'];
        $columns = $allData['header'];
        // $columns = array_shift($mainData);

        $processedData = [];

        foreach ($columns as $index => $c) {

            if (isset($this->variables[$c])) {

                if ($c && isset($mainData[0][$index])) {

                    $formula = ' ' . $mainData[0][$index] . ' ';

                    foreach ($this->variables as $shortcut => $value) {

                        if (strpos($formula, $shortcut) !== false) {

                            foreach ($this->EXCLUDE_REPLACE_VARIABLES as $exName => $exValue) {
                                $formula = str_replace($exName, $exValue, $formula);
                            }

                            $formula = str_replace(' ' . $shortcut . ' ', ' ' . $value['variable_name'] . ' ', $formula);

                            foreach ($this->EXCLUDE_REPLACE_VARIABLES as $exName => $exValue) {
                                $formula = str_replace($exValue, $exName, $formula);
                            }
                        }
                    }

                    $v = $this->variables[$c];

                    $processedData[$v['variable_name']] = [
                        "id" => uniqid(),
                        "type" => "custom",
                        "readable_name" => $v['readable_name'],
                        "variable_name" => $v['variable_name'],
                        "variable_value" => trim($formula)
                    ];
                }
            }
        }

        return $processedData;
    }

    public function validate($variablesSheetData, $sheet)
    {
        $rows = $sheet->toArray(null, true, false);

        $columns = $this->headers;

        $errorFormats = [];

        foreach ($columns as $colIndex => $col) {

            if (strpos($col, 'No.') === false && !isset($variablesSheetData[$col])) {

                $errorFormats[] = ['row' => 0, 'col' => $colIndex + 1, 'name' => $col, 'error' => 'not valid mapping'];
            }
        }

        if (!$errorFormats) return [];

        return [
            'formats' => $errorFormats,
            'startRow' => $this->startRow($rows)
        ];
    }

    public function saveProcessedData($name, $data)
    {

        $fomulas = isset($data['S_CALCULATED_VALUE']) ? $data['S_CALCULATED_VALUE']['variable_value'] : '';

        $saveData = array_filter($data, function ($value) {
            return $value['variable_name'] != 'S_CALCULATED_VALUE';
        });

        $calculationSheetTemplate = CalculationSheetTemplate::create([
            'client_id' => $this->client_id,
            'name' => $name,
            'fomulas' => $fomulas,
            'payment_period' => 'fulltime',
            'display_columns' => json_encode([
                'columns' => array_values($saveData),
                'remainColumns' => [],
                'addedColumns' => []
            ])
        ]);

        return $calculationSheetTemplate->id;
    }
}
