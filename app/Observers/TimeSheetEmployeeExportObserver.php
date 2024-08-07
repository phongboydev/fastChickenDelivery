<?php

namespace App\Observers;

use App\Models\TimeSheetEmployeeExport;
use Illuminate\Support\Facades\Storage;

class TimeSheetEmployeeExportObserver
{

    public function deleted(TimeSheetEmployeeExport $timeSheetEmployeeExport)
    {
        if (Storage::exists($timeSheetEmployeeExport->path)) {
            Storage::delete($timeSheetEmployeeExport->path);
        }
    }

}
