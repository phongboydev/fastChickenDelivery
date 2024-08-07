<?php

namespace App\Exports\Data;

use App\Models\ClientEmployeeCustomVariable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientEmployeeCustomVariableExport implements WithHeadings, FromQuery
{
    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return ClientEmployeeCustomVariable::query();
    }
}
