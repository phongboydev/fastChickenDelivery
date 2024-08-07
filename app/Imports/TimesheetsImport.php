<?php

namespace App\Imports;

use ErrorException;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Exceptions\CustomException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Models\Timesheet;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Support\Constant;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Support\ImportTrait;
use App\Imports\Sheets\TimesheetsImportSheetData;

class TimesheetsImport implements WithMultipleSheets
{
    use Importable;

    protected $client_id = null;
    protected $user = null;

    function __construct($clientId, $user)
    {
        $this->client_id = $clientId;
        $this->user = $user;
    }

    public function sheets(): array
    {
        return [
            'Sheet1' => new TimesheetsImportSheetData($this->client_id, $this->user),
        ];
    }
}
