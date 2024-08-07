<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\CalculationSheetClientEmployee;
use App\Support\MathJSRunner;
use App\Support\ClientHelper;
use Illuminate\Console\Command;

class TestCalculationSheetTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:calculation_sheet_template {calculation_sheet_id} {client_employee_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Calculation Sheet Template';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $calculationSheetId = $this->argument("calculation_sheet_id");
        $clientEmployeeId = $this->argument("client_employee_id");

        $clientEmployee = ClientEmployee::where('id', $clientEmployeeId)->first();
        if ($clientEmployee) {
            $clientId = $clientEmployee->client_id;
            CalculationSheetClientEmployee::query()
                ->where("calculation_sheet_id", $calculationSheetId)
                ->where("client_employee_id", $clientEmployeeId)
                ->chunk(100, function ($csces) use ($clientId) {
                    $mathjs = new MathJSRunner();
                    $mathjs->start();

                    $formulas = ClientHelper::getValidatedFormulas($clientId);

                    $formulas = $formulas->map(function($item){
                        return ["func_name" => $item->func_name, "parameters" => $item->parameters, "formula" => $item->formula];
                    })->all();

                    $mathjs->formulas($formulas);
                    $cs = null;

                    $columns = null;
                    foreach ($csces as $model) {
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

                    $results = $mathjs->getResult();
                    $this->line("List variable name:");
                    foreach ($results as $calculated) {
                        foreach ($columns as $column) {
                            $this->line('- '. $column["variable_name"] .': ' . $calculated[$column["variable_name"]]);
                        }
                    }
                });
        } else {
            $this->line("Client employee does not exist");
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

