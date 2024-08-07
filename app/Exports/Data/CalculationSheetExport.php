<?php

namespace App\Exports\Data;

use App\Models\CalculationSheet;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CalculationSheetExport implements WithHeadings, FromQuery
{
    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return CalculationSheet::query();
    }
}
