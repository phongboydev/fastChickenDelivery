<?php

namespace App\Jobs;

use App\Events\CalculationSheetCalculatedEvent;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\Formula;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Pdfs\CalculationSheetClientEmployeeHtmlToPdf;
use App\Support\MathJSRunner;
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
use Illuminate\Support\Str;

class ProcessUpdateCalculationSheetClientEmployeeJob implements ShouldQueue, ShouldBeUnique
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

        logger('@ProcessUpdateCalculationSheetClientEmployeeJob ' . $clientId, [$formulas]);

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

            foreach ($vars as $var) {
                $mathjs->set($var["variable_name"], $var["variable_value"]);
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
        foreach ($results as $index => $calculated) {
            $model = $this->models[$index];
            $model->calculated_value = $this->defaultZero($calculated["S_CALCULATED_VALUE"]);
            $model->save();
            foreach ($columns as $column) {
                CalculationSheetVariable::query()
                                        ->where('calculation_sheet_id', $model->calculation_sheet_id)
                                        ->where('client_employee_id', $model->client_employee_id)
                                        ->where('variable_name', $column["variable_name"])
                                        ->where('readable_name', $column["readable_name"])
                                        ->update([
                                            'variable_value' =>  $this->defaultZero($calculated[$column["variable_name"]])
                                        ]);
            }
        }

        // Generate Payslip PDF for each Employee
        foreach ($results as $index => $calculated) {
            $model = $this->models[$index];
            $payslipPdf = new CalculationSheetClientEmployeeHtmlToPdf($model);
            dispatch(new GeneratePdfJob($payslipPdf));
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
