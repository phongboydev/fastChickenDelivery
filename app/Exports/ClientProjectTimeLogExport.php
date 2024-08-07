<?php
namespace App\Exports;

use Illuminate\Support\Carbon;


use App\Models\ClientEmployee;
use App\Models\ClientProjectTimelog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ClientProjectTimeLogExport implements FromCollection,WithHeadings,WithMapping

{

    private $client_project_id = null;
    private $filter = '';
    private $filterStartDate = '';
    private $filterEndDate = '';
    private $filterClientEmployeeId = '';

    function __construct($client_project_id, $filter, $filterStartDate, $filterEndDate, $filterClientEmployeeId)
    {
        $this->client_project_id = $client_project_id;
        $this->filter = $filter;
        $this->filterStartDate = $filterStartDate;
        $this->filterEndDate = $filterEndDate;
        $this->filterClientEmployeeId = $filterClientEmployeeId;
    }

    public function collection()
    {
        $query = ClientProjectTimelog::leftJoin('client_employees', 'client_project_timelogs.client_employee_id', 'client_employees.id')
            ->select('client_project_timelogs.*', 'client_employees.full_name', 'client_employees.code', 'client_employees.salary')
            ->where('client_project_id', $this->client_project_id);
        if($this->filter != '') {
            $query->where('client_employees.full_name', 'LIKE', '%'.$this->filter.'%');
            $query->orWhere('client_employees.code', 'LIKE', '%'.$this->filter.'%');
        }
        if($this->filterStartDate != '') {
            $query->where('log_date', '>=', $this->filterStartDate);
        }
        if($this->filterEndDate != '') {
            $query->where('log_date', '<=', $this->filterEndDate);
        }
        if($this->filterClientEmployeeId != '') {
            $query->where('client_employees.id', $this->filterClientEmployeeId);
        }
        return $query->get();
    }
    /**
     * Returns headers for report
     * @return array
     */
    public function headings(): array {
        return [
            'Employee',
            'Date',
            'Working hours',    
            "Document type",
            "Estimated salary"
        ];
    }
 
    public function map($clientProjectTimelogs): array {
        return [
            '['.$clientProjectTimelogs->code.'] '.$clientProjectTimelogs->full_name ,
            $clientProjectTimelogs->log_date,
            $clientProjectTimelogs->work_hours,
            $clientProjectTimelogs->work_type,
            $clientProjectTimelogs->salary * $clientProjectTimelogs->work_hours 
        ];
    }

}
