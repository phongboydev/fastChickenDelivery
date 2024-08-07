<?php

namespace App\Exports\Data;

use App\Models\CalculationSheetVariable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CalculationSheetVariableExport implements FromQuery, WithHeadings
{
    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return CalculationSheetVariable::query();
    }
}
