<?php

namespace App\GraphQL\Mutations;

use App\Exports\TimesheetShiftEmployeeVersionAdvanceExport;
use App\Exports\TimesheetShiftEmployeeVersionExport;
use App\Jobs\DeleteFileJob;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetShift;
use App\Support\Constant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TimeSheetShiftMutator
{
    public function deleteByGroup($_, array $args)
    {
        $groupIds = $args['ids'];
        if (!is_array($groupIds)) {
            return 'error';
        }

        foreach ($groupIds as $id) {
            $model = TimesheetShift::findOrFail($id);
            $model->delete();
        }

        return 'success';
    }

    public function exportShiftHistoryByVersion($_, array $args)
    {
        $setting = ClientWorkflowSetting::where('client_id', Auth::user()->client_id)
            ->select('enable_timesheet_shift_template_export')
            ->first();
        $template_type = !empty($setting->enable_timesheet_shift_template_export) ? ($args['type'] ?? "default") : "default";

        if ($template_type == 'advance') {
            $fileName = "TIMESHEET_SHIFT_HISTORY_VERSION_ADVANCE__" . uniqid() .  '.xlsx';
            $pathFile = 'TimesheetShiftExport/' . $fileName;
            Excel::store((new TimesheetShiftEmployeeVersionAdvanceExport($args['versions'])), $pathFile, 'minio');

            $response = [
                'name' => $fileName,
                'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
            ];
        } else {
            $fileName = "TIMESHEET_SHIFT_HISTORY_VERSION__" . uniqid() .  '.xlsx';
            $pathFile = 'TimesheetShiftExport/' . $fileName;
            Excel::store((new TimesheetShiftEmployeeVersionExport($args['versions'])), $pathFile, 'minio');

            $response = [
                'name' => $fileName,
                'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
            ];

        }

        // Delete file
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode($response);
    }
}
