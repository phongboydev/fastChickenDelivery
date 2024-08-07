<?php

namespace App\Imports;

use App\Models\CalculationSheetTemplateAssignment;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use App\Support\ImportTrait;

class CalculationSheetTemplateAssignmentImport implements ToCollection, WithHeadingRow, WithStartRow
{
    use Importable, ImportTrait;
    protected $templateID = null;
    protected $clientID = null;

    protected const RIGHT_HEADER = [
        "code" => ['code_employee_exists', 'required'],
        "full_name" => ['string'],
        "sort_by" => ['string', 'required'],
    ];

    function __construct($templateID, $clientId)
    {
        $this->templateID = $templateID;
        $this->clientID = $clientId;
    }

    public function collection(Collection $rows)
    {
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
        try {
            if (!empty($this->templateID)) {
                foreach ($filteredData as $row) {
                    CalculationSheetTemplateAssignment::where('template_id', $this->templateID)
                        ->whereHas('clientEmployee', function ($query) use ($row) {
                            $query->where('code', $row['code']);
                        })->update(['sort_by' => $row['sort_by']]);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            logger("error: " . $e->getMessage());
            DB::rollBack();
        }

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
        return 3;
    }

    public function endRow($rows): int
    {
        return -1;
    }

    public function startHeader(): int
    {
        return 1;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function totalCol(): int
    {
        return 3;
    }

    public function getRightHeader()
    {
        return self::RIGHT_HEADER;
    }

    public function getClientID()
    {
        return $this->clientID;
    }

}
