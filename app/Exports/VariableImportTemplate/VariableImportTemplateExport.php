<?php

namespace App\Exports\VariableImportTemplate;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\ClientEmployeeStatusExportDauKy;

class VariableImportTemplateExport implements WithMultipleSheets
{
    use Exportable;

    protected $variables = [];
    protected $client;
    
    function __construct($variables, $client)
    {
        $this->variables = $variables;
        $this->client = $client;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        $sheets[] = new VariableImportSheet( $this->variables, $this->client);
        return $sheets;
    }
}