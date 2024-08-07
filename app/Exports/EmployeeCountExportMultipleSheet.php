<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\EmployeeCountExport;

class EmployeeCountExportMultipleSheet implements WithMultipleSheets
{

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->data['clients'] as $c => $clients) {
            $sheets[] = new EmployeeCountExport($clients);
        }

        return $sheets;
    }
}
