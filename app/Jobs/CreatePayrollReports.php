<?php

namespace App\Jobs;

use App\User;
use App\Models\ReportPayroll;
use App\Models\CalculationSheet;
use App\Models\ClientEmployee;
use App\Models\CalculationSheetClientEmployee;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PayrollReportAllExport;
use App\Notifications\ReportPayrollsNotification;
class CreatePayrollReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    protected $from_date;
    protected $to_date;

    /**
     * Create a new job instance.
     *
     * @param SurveyJob           $job
     * @param SurveyJobSubmission $submission
     * @param array               $subjects
     * @param array               $htmls
     * @param string|null         $emailOverride
     */
    public function __construct($from_date, $to_date)
    {  
        $this->from_date = $from_date;
        $this->to_date = $to_date;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $from_date = $this->from_date;
        $to_date = $this->to_date;

        $calculationSheets = CalculationSheet::select('id')
            ->where(function($subQuery) use ($from_date, $to_date) {
                $subQuery->whereBetween('date_from', [$from_date, $to_date])
                        ->orWhereBetween('date_to', [$from_date, $to_date]);
            })->whereIn('status', ['paid', 'client_approved'])->get()->pluck('id')->all();
        
        $results = [];
        $chunks = config('app.payroll_report_chunks', 600);

        CalculationSheetClientEmployee::select('client_employee_id')
                        ->whereIn('calculation_sheet_id', $calculationSheets)
                        ->chunkById($chunks, function ($employees) use(&$results) {
                            
                            $ids = $employees->pluck('client_employee_id')->all();

                            $rs = ClientEmployee::select(
                                'position',
                                'career',
                                DB::raw('YEAR(date_of_birth) AS year_of_birth'),
                                DB::raw('SUM(salary) AS luong'),
                            )
                                ->whereIn('id', $ids)
                                ->groupBy('position', 'year_of_birth', 'career')
                                ->get()->toArray();
                            
                            foreach( $rs as $r ) {

                                $key = md5(serialize([
                                    'year_of_birth' => $r['year_of_birth'],
                                    'position' => $r['position'],
                                    'career' => $r['career']
                                ]));

                                if(isset($results[$key])) {
                                    $results[$key]['luong'] += $r['luong'];
                                }else{
                                    $results[$key] = $r;
                                }

                                $results[$key]['luong_binh_quan'] = round($results[$key]['luong'] / count($rs), 1);
                            }

                        }, 'id');
        
        $pathFile = 'PayrollReport/report_' . $from_date . '_' . $to_date . '.xlsx';

        Excel::store((new PayrollReportAllExport(array_values($results))), $pathFile, 'minio');

        $reportPayroll = ReportPayroll::where('date_from', $from_date)->where('date_to', $to_date)->first();

        $reportPayroll->addMediaFromDisk($pathFile, 'minio')
                    ->toMediaCollection('ReportPayroll', 'minio');

        ReportPayroll::where('date_from', $from_date)->where('date_to', $to_date)->update(['status' => 'completed']);

        $creator = User::where('id', $reportPayroll->original_creator_id)->first();

        if( !empty($creator) ) {
            $creator->notify(new ReportPayrollsNotification($reportPayroll));
        }
    }
}
