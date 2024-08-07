<?php

namespace App\Jobs;

use App\Events\CalculationSheetCalculatedEvent;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\WorkTimeRegisterLog;
use App\Models\WorkTimeRegisterTimesheet;
use App\Pdfs\CalculationSheetClientEmployeeHtmlToPdf;
use App\Support\MathJSRunner;
use Carbon\Carbon;
use App\Support\ClientHelper;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessCalculationSheetClientEmployeeJob implements ShouldQueue, ShouldBeUnique
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Collection $models;
    private $clientId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Collection $models, $clientId)
    {
        $this->models = $models;
        $this->clientId = $clientId;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $clientId = $this->clientId;
        $mathjs = new MathJSRunner();
        $mathjs->start();

        $formulas = ClientHelper::getValidatedFormulas($clientId);

        $formulas = $formulas->map(function($item){
            return ["func_name" => $item->func_name, "parameters" => $item->parameters, "formula" => $item->formula];
        })->all();

        logger('@ProcessCalculationSheetClientEmployeeJob ' . $clientId, [$formulas]);

        $mathjs->formulas($formulas);
        $cs = null;
        // {
        //     "id": 1608863397682,
        //     "type": "custom",
        //     "readable_name": "Salary per hour",
        //     "variable_name": "F_HOUR_WAGES",
        //     "variable_value": "F_ROUND_1 ( S_SALARY / S_STANDARD_WORK_HOURS , 0 )"
        // }
        $columns = null;
        foreach ($this->models as $model) {
            /** @var CalculationSheetClientEmployee $model */
            if (!$columns) {
                $cs = $model->calculationSheet;
                $displayColumns = json_decode($cs->display_columns, true);
                $columns = $displayColumns["columns"] ?? [];
                $columns[] = [
                    "readable_name" => "Calculated value",
                    "variable_name" => "S_CALCULATED_VALUE",
                    "variable_value" => $cs->fomulas,
                ];
            }
            $vars = CalculationSheetVariable::query()
                                            ->select([
                                                "id",
                                                "variable_name",
                                                "variable_value",
                                            ])
                                            ->where("calculation_sheet_id", $model->calculation_sheet_id)
                                            ->where("client_employee_id", $model->client_employee_id)
                                            ->get()
                                            ->toArray();

             // Get PREVIOUS_S_ from multiple_variables setting.
            $multiple_variables = [];
            if (is_array($cs->multiple_variables)) {
                foreach ($cs->multiple_variables as $value) {
                    $multiple_variables['PREVIOUS_' . $value] = 'PREVIOUS_' . $value;
                }
            }

            foreach ($vars as $var) {
                $mathjs->set($var["variable_name"], $var["variable_value"]);
                if (isset($multiple_variables[$var["variable_name"]])) {
                    unset($multiple_variables[$var["variable_name"]]);
                }
            }

            /**
             * The multiple_variables setting is existed,
             * but not all users have over 1 salary range.
             * We need to set them are 0, this make sure nodejs to run.
             */
            if (!empty($multiple_variables)) {
                foreach ($multiple_variables as $variable_name) {
                    $mathjs->set($variable_name, "0");
                }
            }

            foreach ($columns as $var) {
                $mathjs->setf($var["variable_name"], $var["variable_value"]);
            }

            $mathjs->calc();
        }

        if (!$cs) {
            logger(self::class . ": CalculationSheet was not provided. Job skip.");
            return;
        }

        // Enable this to get debug input
        // if (config("app.debug")) {
        //     Storage::disk("local")->put(
        //         "calculation_sheet_" . time() . "_input.log",
        //         implode("\n", $mathjs->getLogs())
        //     );
        // }

        $results = $mathjs->getResult();
        logger("ProcessCalculationSheetClientEmployee@handle", [
            // "mathjs_results" => $results,
        ]);
        // Each item is for one CalculationSheetClientEmployee
        $massUpdates = [];
        $otherFrom = $cs->other_from ?? $cs->date_from;
        $otherTo = $cs->other_to ?? $cs->date_to;
        foreach ($results as $index => $calculated) {
            $model = $this->models[$index];
            $model->calculated_value = $this->defaultZero($calculated["S_CALCULATED_VALUE"]);
            $model->completed = true;
            $model->save();
            $this->storeWorkTimeRegisterLog($otherFrom, $otherTo, $cs->month, $cs->year, $model->client_employee_id, $model->id);
            foreach ($columns as $column) {
                $massUpdates[] = [
                    "id" => DB::raw("UUID()"),
                    "calculation_sheet_id" => $model->calculation_sheet_id,
                    "client_employee_id" => $model->client_employee_id,
                    "variable_name" => $column["variable_name"],
                    "variable_value" => $this->defaultZero($calculated[$column["variable_name"]]),
                    "readable_name" => $column["readable_name"],
                ];
            }
        }
        DB::table("calculation_sheet_variables")->insert($massUpdates);

        // Generate Payslip PDF for each Employee
        foreach ($results as $index => $calculated) {
            $model = $this->models[$index];
            $payslipPdf = new CalculationSheetClientEmployeeHtmlToPdf($model);
            dispatch(new GeneratePdfJob($payslipPdf));
        }

        $hasUnfinishedRecord = CalculationSheetClientEmployee::query()
                                                             ->where("calculation_sheet_id", $cs->id)
                                                             ->where("completed", false)
                                                             ->exists();

        if (!$hasUnfinishedRecord) {
            logger(self::class.': CalculationSheet is completed. Updating status.');
            if ($cs->is_internal) {
                $cs->status = "new";
            } else {
                $approveFlow = ApproveFlow::where('flow_name', 'CLIENT_REQUEST_PAYROLL')->where('client_id', $cs->client_id)->get();
                $cs->status = "client_review";
                if ($approveFlow->isNotEmpty()) {
                    foreach($approveFlow as $item) {
                        $userFlow = ApproveFlowUser::where('approve_flow_id', $item['id'])->get();
                        if($userFlow->isEmpty()){
                            $cs->status = "error";
                            $cs->error_message = __("notifications.request_config_payroll");
                        }
                    }
                } else {
                    $cs->status = "error";
                    $cs->error_message = __("notifications.request_config_payroll");
                }
            }
            $cs->save();
            event(new CalculationSheetCalculatedEvent($cs));
        }
    }

    /**
     *
     * @param  $otherFrom
     * @param  $otherTo
     * @param integer $month
     * @param integer $year
     * @param string  $employeeID
     * @param string  $calSheetEmployeeID
     */
    private function storeWorkTimeRegisterLog($otherFrom, $otherTo, $month, $year, $employeeID, $calSheetEmployeeID)
    {
        $wrts = WorkTimeRegisterTimesheet::select("id")
            ->whereHas('timesheet', function($q) use($otherFrom, $otherTo) {
                $q->whereBetween('log_date', [
                    $otherFrom,
                    $otherTo,
                ]);
            })
            ->where('client_employee_id', $employeeID)
            ->where(function ($sub_query2) use ($month, $year) {
                $sub_query2->where(function($sub_query3) use ($month, $year) {
                    $sub_query3->where('month_lock', $month);
                    $sub_query3->where('year_lock', $year);
                });
                $sub_query2->orWhere(function($sub_query3) {
                    $sub_query3->where('month_lock', 0);
                    $sub_query3->where('year_lock', 0);
                });
            })->get();

        $storeData = [];

        foreach ($wrts as $wrt) {
            $storeData[] = [
                "id" => DB::raw("UUID()"),
                "cal_sheet_client_employee_id" => $calSheetEmployeeID,
                "work_time_register_timesheet_id" => $wrt->id,
                "created_at" => Carbon::now()->format('Y-m-d H:i:s'),
                "updated_at" => Carbon::now()->format('Y-m-d H:i:s'),
            ];
        }
        if (!empty($storeData)) {
            WorkTimeRegisterLog::insert($storeData);
        }
    }

    protected function defaultZero($value)
    {
        if ($value == "NaN" || $value == "Infinity") {
            return 0;
        }
        return $value;
    }
}
