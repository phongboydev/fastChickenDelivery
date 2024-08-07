<?php

namespace App\Exports;

use App\Models\ClientEmployee;
use App\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\TimesheetSheet;
use App\Exports\Sheets\TimesheetTotalSheet;
use App\Exports\Sheets\TimesheetWorkinghoursSheet;
use App\Exports\Sheets\TimesheetOvertimehoursSheet;

class TimesheetEmployeeExport implements WithMultipleSheets
{
    protected $clientEmployeeIds;
    protected $date;
    protected $wt_category;
    protected $wt_category_by_id;
    protected $wt_category_list;
    protected $lang;

    public function __construct($clientEmployeeIds, $fromDate, $toDate, $wt_category, $wt_category_by_id, $wt_category_list, $lang = 'en')
    {
        $this->clientEmployeeIds = $clientEmployeeIds;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->wt_category = $wt_category;
        $this->wt_category_by_id = $wt_category_by_id;
        $this->wt_category_list = $wt_category_list;
        $this->lang = $lang;
    }

    public function sheets(): array
    {
        app()->setlocale($this->lang);

        $sheets = [];
        // Get template
        $templateExport = 1;
        if(count($this->clientEmployeeIds) > 0) {
            $employee = ClientEmployee::where("id", $this->clientEmployeeIds[0])->first();
            $templateExport = optional($employee->client->clientWorkflowSetting->template_export)['timesheet'] ?? 1;
        }

        $sheets[] = new TimesheetTotalSheet($this->clientEmployeeIds, $this->fromDate, $this->toDate, $this->wt_category, $this->wt_category_by_id, $templateExport);
        $sheets[] = new TimesheetWorkinghoursSheet($this->clientEmployeeIds, $this->fromDate, $this->toDate);
        $sheets[] = new TimesheetOvertimehoursSheet($this->clientEmployeeIds, $this->fromDate, $this->toDate);
        foreach ($this->clientEmployeeIds as $employeeId) {
            $sheets[] = new TimesheetSheet($employeeId, $this->fromDate, $this->toDate, $this->wt_category_list, $templateExport);
        }

        return $sheets;
    }
}
