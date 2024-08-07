<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
// use Illuminate\Support\Facades\Storage;
use App\Exports\TimesheetEmployeeExport;
use App\Models\TimeSheetEmployeeExport as TimeSheetEmployeeExportModel;
use Throwable;
use App\Models\WorktimeRegisterCategory;
use App\Support\Constant;

class TimeSheetEmployeeExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $clientEmployeeIds;
    protected $fromDate;
    protected $toDate;
    protected $exportId;
    protected $wt_category;
    protected $wt_category_list;
    protected $wt_category_by_id;
    protected $lang;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($clientEmployeeIds, $fromDate, $toDate, $exportId, $lang)
    {
        $this->clientEmployeeIds = $clientEmployeeIds;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->exportId = $exportId;

        $wt_category = collect(WorktimeRegisterCategory::select('id', 'category_name', 'sub_type')->where('client_id', auth()->user()->client_id)->get()->toArray());
        $this->wt_category_list = $wt_category;
        $grouped = $wt_category->mapToGroups(function ($item, $key) {
            return [$item['sub_type'] => $item['category_name']];
        });

        $grouped_id = $wt_category->mapToGroups(function ($item, $key) {
            return [$item['sub_type'] => $item['id']];
        });

        $this->wt_category = array_merge_recursive(Constant::LEAVE_CATEGORIES, $grouped->toArray());
        $this->wt_category_by_id = array_merge_recursive(Constant::LEAVE_CATEGORIES_BY_KEY, $grouped_id->toArray());
        $this->lang = $lang;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Export excel
        $extension = '.xlsx';
        $fileName = "TOTAL_TIMESHEET_EMPLOYEES_" . uniqid() .  $extension;
        $pathFile = 'TimeSheetClientEmployeeExport/' . $fileName;

        Excel::store((new TimesheetEmployeeExport($this->clientEmployeeIds, $this->fromDate, $this->toDate, $this->wt_category, $this->wt_category_by_id, $this->wt_category_list, $this->lang)), $pathFile, 'minio');
        TimeSheetEmployeeExportModel::where('id', $this->exportId)->update([
            'path' => $pathFile,
            'status' => 'downloadable'
        ]);
    }

    public function failed(Throwable $exception)
    {
        // Send user notification of failure, etc...
        TimeSheetEmployeeExportModel::where('id', $this->exportId)->update([
            'status' => 'error'
        ]);
    }
}
