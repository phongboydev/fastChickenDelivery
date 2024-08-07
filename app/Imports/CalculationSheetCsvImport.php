<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Exports\CalculationSheetSalaryExportFromTemplate;
use Illuminate\Http\File;

class CalculationSheetCsvImport implements ToCollection
{
    public $data;
    public function collection(Collection $rows)
    {
        $this->data = $rows;
    }
}