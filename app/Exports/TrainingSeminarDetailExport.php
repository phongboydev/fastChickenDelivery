<?php

namespace App\Exports;

use App\Exports\Sheets\TrainingSeminarAttendanceForClient;
use App\Exports\Sheets\TrainingSeminarAttendance;
use App\Exports\Sheets\TrainingSeminarInfo;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class TrainingSeminarDetailExport implements WithMultipleSheets
{
    use Exportable;
    protected $params;

    public function __construct($params = [])
    {
        $this->params = $params;
    }

    public function sheets(): array
    {
        return [
            new TrainingSeminarInfo($this->params),
            new TrainingSeminarAttendance($this->params)
        ];
    }
}
