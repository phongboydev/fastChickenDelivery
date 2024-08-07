<?php

namespace App\Imports;

use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetTmp;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Events\BeforeSheet;

class SpecialTimesheetImport implements ToCollection, SkipsOnFailure, WithValidation, WithStartRow, WithColumnLimit, WithEvents, WithChunkReading
{
    use SkipsFailures;

    protected string $client_id;
    protected string $sheet_name;
    protected string $import_key;
    protected $clientEmployees;
    protected $clientEmployeesImport;

    /**
     * @param  string $client_id
     * @param  string $user_id
     */
    public function __construct(
        string $client_id,
        string $user_id
    ) {
        $this->client_id = $client_id;
        $this->sheet_name = '';
        $this->clientEmployees = ClientEmployee::select(['id', 'code'])->where('client_id', $this->client_id)->get()->keyBy('code');
        $this->import_key = $user_id . "_" . time();
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $this->sheet_name = $event->getSheet()->getTitle();
            }
        ];
    }

    public function getSheetName(): string
    {
        return $this->sheet_name;
    }

    public function getImportKey(): string
    {
        return $this->import_key;
    }

    public function getClientEmployeesImport()
    {
        return $this->clientEmployeesImport;
    }

    public function collection(Collection $rows)
    {
        $tmp = [];
        $this->clientEmployeesImport = [];
        foreach ($rows as $row) {
            if ($clientEmployee = $this->clientEmployees->get((string)$row[0])) {
                $tmp[] = [
                    'import_key' => $this->import_key,
                    'client_employee_id' => $clientEmployee->id,
                    'check_in' => $row[4],
                    'check_out' => $row[6]
                ];
                $this->clientEmployeesImport[] = $clientEmployee->id;
            }
        }

        TimesheetTmp::insert($tmp);
    }

    public function rules(): array
    {
        return [];
//        return [
//            '4' => ['date'],
//            '6' => ['date']
//        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [];
//        return [
//            '4.date' => "Re-check with format 'Y-m-d H:i:s'",
//            '6.date' => "Re-check with format 'Y-m-d H:i:s'",
//        ];
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 3;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function endColumn(): string
    {
        return "G";
    }
}
