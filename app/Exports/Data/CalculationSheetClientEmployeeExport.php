<?php

namespace App\Exports\Data;

use App\Models\CalculationSheetClientEmployee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CalculationSheetClientEmployeeExport implements FromQuery, WithHeadings
{
    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return CalculationSheetClientEmployee::query();
    }
}
