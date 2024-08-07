<?php

namespace App\Exports\Data;

use App\Models\ClientEmployee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientEmployeeExport implements FromQuery, WithHeadings
{

    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return ClientEmployee::query();
    }
}
