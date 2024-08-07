<?php

namespace App\Exports;

use App\Models\ClientDepartment;
use Maatwebsite\Excel\Excel;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;

class ClientDepartmentExport implements FromCollection, Responsable
{
    use Exportable;

    protected $client_id = null;

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    /**
     * It's required to define the fileName within
     * the export class when making use of Responsable.
     */
    private $fileName = 'client_department.xlsx';

    /**
     * Optional Writer Type
     */
    private $writerType = Excel::XLSX;

    /**
     * Optional headers
     */
    private $headers = [
        'Content-Type' => 'text/csv',
    ];

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return ClientDepartment::select('department', 'code')->where('client_id', $this->client_id)->orderBy('created_at', 'desc')->get();
    }
}
