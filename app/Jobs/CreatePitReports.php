<?php

namespace App\Jobs;

use App\Models\ImportablePITEmployee;
use App\Models\ReportPit;
use App\Models\CalculationSheet;
use App\Models\Client;
use App\Models\CalculationSheetVariable;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PitReportExport;

class CreatePitReports implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportPitID;
    protected $payrolls;
    protected $columnVariables;
    protected $clientID;
    protected $isDeviated;

    /**
     * Create a new job instance.
     *
     * @param SurveyJob           $job
     * @param SurveyJobSubmission $submission
     * @param array               $subjects
     * @param array               $htmls
     * @param string|null         $emailOverride
     */
    public function __construct($clientID, $reportPitID, $payrolls, $columnVariables, $isDeviated)
    {
        $this->reportPitID = $reportPitID;
        $this->payrolls = $payrolls;
        $this->columnVariables = $columnVariables;
        $this->clientID = $clientID;
        $this->isDeviated = $isDeviated;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $client = Client::where('id', $this->clientID)->first();
            $reportPayroll = ReportPit::where('id', $this->reportPitID)->first();

            $calculationSheets = CalculationSheet::select(['id', 'client_id', 'month', 'year'])
                ->whereIn('id', $this->payrolls)->orderBy('date_from', 'ASC')->get();

            $groupedCal = $calculationSheets->groupBy(function ($item, $key) {
                if ($this->isDeviated) {
                    if ($item["month"] == 12) {
                        return "1/" . ((int)$item["year"] + 1);
                    } else {
                        return ((int)$item["month"] + 1) . "/" . $item["year"];
                    }
                } else {
                    return $item["month"] . "/" . $item["year"];
                }
            });

            $clientEmployees = [];

            $clientEmployeeVariablesAll = [];
            $chunks = config('app.pit_report_chunks', 100);

            foreach ($groupedCal as $groupedName => $cal) {

                $payrolls = $cal->pluck('id')->all();
                $clientEmployeeVariables = [];
                $additionalColumn = [
                    "S_RESIDENT_STATUS"
                ];
                $queryColumnVariables = array_merge($this->columnVariables, $additionalColumn);
                CalculationSheetVariable::select(['id', 'client_employee_id', 'variable_name', 'variable_value', 'calculation_sheet_id'])
                    ->whereIn('calculation_sheet_id', $payrolls)
                    ->whereIn('variable_name', $queryColumnVariables)
                    ->with('clientEmployee.dependentsInformation')->groupBy('client_employee_id', 'variable_name', 'calculation_sheet_id')
                    ->chunkById($chunks, function ($calculationSheetVariables) use (&$clientEmployeeVariables, &$clientEmployees, $groupedName) {

                        foreach ($calculationSheetVariables as $v) {

                            $e = $v->clientEmployee;

                            $clientEmployees[$e['code']] = $e;
                            $clientEmployeeVariables[$groupedName][$e['code']][$v->variable_name][] = $v->variable_value;
                        }
                    }, 'id');

                $clientEmployeeVariablesAll = array_merge($clientEmployeeVariablesAll, $clientEmployeeVariables);
            }

            $pathFile = 'ReportPIT/' . strtolower($reportPayroll->name) . '_' . time() . '.xlsx';
            usort($clientEmployees, function ($item1, $item2) {
                return $item1['code'] <=> $item2['code'];
            });

            $periods = $this->getMonthAndYear($reportPayroll);

            $startTime = Carbon::createFromDate($periods['from']['year'], $periods['from']['month'], 1);
            $endTime = Carbon::createFromDate($periods['to']['year'], $periods['to']['month'], 1);

            while ($endTime->greaterThanOrEqualTo($startTime)) {
                $time = $startTime->month . '/' .  $startTime->year;
                if (empty($groupedCal[$time])) {
                    $temporaryTime = $this->isDeviated ? $startTime->clone()->subMonth() : $startTime;
                    $groupedCal[$time] = [[
                        'month' => $temporaryTime->month,
                        'year' => $temporaryTime->year
                    ]];
                }
                $startTime->addMonth();
            }
            $sortedCal = $groupedCal->sortBy(function ($cal, $key) {
                return \DateTime::createFromFormat('n/Y', $key);;
            });

            $importableEmployees = ImportablePITEmployee::with(['importablePITData' => function ($query) use($periods) {
                $query->whereBetween('month', [$periods['from']['month'], $periods['to']['month']])
                    ->whereBetween('year', [$periods['from']['year'], $periods['to']['year']]);
            }])->whereHas('importablePITData', function($query) use($periods) {
                    $query->whereBetween('month', [$periods['from']['month'], $periods['to']['month']])
                        ->whereBetween('year', [$periods['from']['year'], $periods['to']['year']]);
            })->where('client_id',$this->clientID)->get();

            foreach ($importableEmployees as $employee) {
                if (empty($clientEmployees[$employee->code])) {
                    $biggestMonthData = $employee->importablePITData->firstWhere('month', $periods['to']['month']);
                    $residentStatus = !empty($biggestMonthData->resident_status) ? $biggestMonthData->resident_status : 0;

                    $clientEmployees[$employee->code] = [
                        'code' => $employee->code,
                        'full_name' => $employee->full_name,
                        'mst_code' => $employee->tax_code,
                        'id_card_number' => $employee->id_number,
                        'client_id' => $employee->client_id,
                        'nationality' => '',
                        'type_of_employment_contract' => '',
                        'resident_status' => $residentStatus
                    ];
                }

                foreach ($employee->importablePITData as $data) {
                    $time = $data->month . '/' .  $data->year;
                    if (empty($clientEmployeeVariablesAll[$time][$employee->code])) {
                        $push = [];
                        $push[$this->columnVariables['tnct_luy_tien']] = [$data->taxable_income];
                        $push[$this->columnVariables['tnch_khautru']] = [$data->taxable_income];
                        $push[$this->columnVariables['tnch_khautru_20']] = [$data->taxable_income];
                        $push[$this->columnVariables['so_nguoi_phuthuoc']] = [$data->number_of_dependants];
                        $push[$this->columnVariables['giam_tru_giacanh_tungthang']] = [$data->deduction_for_dependants];
                        $push[$this->columnVariables['bhbb_do_nld_tra']] = [$data->compulsory_social_insurance];
                        $push[$this->columnVariables['thu_nhap_tinhthue']] = [$data->assessable_income];
                        $push[$this->columnVariables['pit_theo_bangluong_luytien']] = [$data->pit];
                        $push[$this->columnVariables['pit_theo_bangluong_khautru']] = [$data->pit];
                        $push[$this->columnVariables['pit_theo_bangluong_khautru_20']] = [$data->pit];
                        $push['S_RESIDENT_STATUS'] = [$data->resident_status];

                        $clientEmployeeVariablesAll[$time][$employee->code] = $push;
                    }
                }
            }

            Excel::store((new PitReportExport($reportPayroll, $client, $this->columnVariables, $sortedCal, $clientEmployees, $clientEmployeeVariablesAll)), $pathFile, 'minio');

            $reportPayroll->addMediaFromDisk($pathFile, 'minio')
                ->toMediaCollection('ReportPIT', 'minio');
            $reportPayroll->export_status = 'completed';
            $reportPayroll->save();
        } catch (\Exception $e) {
            ReportPit::where('id', $this->reportPitID)->update(['export_status' => 'error']);
            throw $e;
        }
    }

    private function getMonthAndYear(ReportPit $reportPayroll): array
    {
        $month_from = $year_from = $month_to = $year_to = 0;

        switch ($reportPayroll->duration_type) {
            case 'thang':
                $periods = explode('-', $reportPayroll->thang_value);
                $month_from = $month_to = (int)$periods[0];
                $year_from = $year_to = (int)$periods[1];
                break;
            case 'quy':
                $month_to = $reportPayroll->quy_value * 3;
                $month_from = $month_to - 2;
                $year_from = $year_to = (int)$reportPayroll->quy_year;
                break;
            case 'nam':
                $month_from = 1;
                $month_to = 12;
                $year_from = $year_to = (int)$reportPayroll->quy_year;
                break;
        }

        return [
            'from' => [
                'month' => $month_from,
                'year'  => $year_from
            ],
            'to' => [
                'month' => $month_to,
                'year'  => $year_to
            ]
        ];
    }
}
