<?php

namespace App\Listeners;

use App\Events\CalculationSheetCalculatedEvent;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use App\Models\Formula;
use App\Models\PayrollAccountantExportTemplate;
use App\Models\PayrollAccountantTemplate;
use App\Support\MathJSRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use App\Support\ClientHelper;

class ProcessPayrollAccountantListener implements ShouldQueue
{

    public function __construct()
    {
        //
    }

    public function handle(CalculationSheetCalculatedEvent $event)
    {
        $cs = $event->calculationSheet;

        // payroll accountant template is save in "payslip_accountant_columns_setting"
        // TODO make column to hold PayrollAccountantTemplate.id
        $accountantSettings = json_decode($cs->payslip_accountant_columns_setting, true) ?? [];
        if (!$accountantSettings || !isset($accountantSettings["template"])) {
            return false;
        }
        $templateId = $accountantSettings["template"];
        logger(self::class . ": accountant export template ID=" . $templateId);
        $paet = PayrollAccountantExportTemplate::where("client_id", $cs->client_id)
                                               ->where("id", $templateId)
                                               ->first();

        if (!$paet) {
            logger()->warning(self::class . ": Can not process accoutant export for CalculationSheet=" . $cs->id . ", Reason: Export template not found");
            return false;
        }

        if (!$paet->payrollAccountantTemplate) {
            logger()->warning(self::class . ": Can not process accoutant export for CalculationSheet=" . $cs->id . ", Reason: Template not found");
            return false;
        }

        $pat = $paet->payrollAccountantTemplate;
        if (!$pat->template_columns) {
            logger()->warning(self::class . ": Can not process accoutant export for CalculationSheet=" . $cs->id . ", Reason: Template is not setup");
            return false;
        }

        switch ($pat->group_type) {
            case "department":
                $values = $this->processByDepartment();
                break;
            case "employee":
                $values = $this->processByEmployee();
                break;
            case "condition_employee":
                $values = $this->processByConditionEmployee($cs, $pat);
                break;
        }

        // persist calculated values
        $accountantSettings["values"] = $values;
        $cs->payslip_accountant_columns_setting = json_encode($accountantSettings);
        return $cs->save();
    }

    protected function processByDepartment(): array
    {
        return [];
    }

    protected function processByEmployee(): array
    {
        return [];
    }

    protected function processByConditionEmployee(CalculationSheet $cs, PayrollAccountantTemplate $pat): array
    {
        $columns = $pat->template_columns;
        $values = [];

        // Setup array to hold template values
        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (!isset($column["readable_name"]) || !isset($column["variable_value"]) || !isset($column["condition"])) {
                    logger(self::class . ": Accountant column skipped due to lack of data.", ["column" => $column]);
                    continue;
                }
                $readableName = $column["readable_name"];
                $variableName = $column["variable_name"];
                $variableValue = $column["variable_value"];
                $condition = $column["condition"];

                $values[] = [
                    "readable_name" => $readableName,
                    "variable_name" => $variableName,
                    "variable_value" => $variableValue,
                    "condition" => $condition,
                    "value" => 0,
                    "calculated" => false
                ];
            }
        }

        // Setup MathJS

        $formulas = ClientHelper::getValidatedFormulas($cs->client_id);

        $formulas = $formulas->map(function($item){
            return ["func_name" => $item->func_name, "parameters" => $item->parameters, "formula" => $item->formula];
        })->all();

        logger('@ProcessPayrollAccountantListener ' . $cs->client_id, [$formulas]);

        $mathJs = new MathJSRunner();

        // calculate variables by loop through every employees
        $cs->calculationSheetClientEmployees()
           ->select(["client_employee_id", "calculated_value"])
           ->chunk(100, function ($ces) use (&$values, $cs, $mathJs, $formulas) {
               $mathJs->start();
               $mathJs->formulas($formulas);
               foreach ($ces as $ce) {
                   /** @var CalculationSheetClientEmployee $ce */
                   $calculationSheetVariables = CalculationSheetVariable::where("calculation_sheet_id", $cs->id)
                                                                        ->where("client_employee_id", $ce->client_employee_id)
                                                                        ->get();

                   foreach ($calculationSheetVariables as $variable) {
                       $mathJs->set($variable->variable_name, $variable->variable_value);
                   }
                   // TODO legacy fallback, user should use S_CALCULATED_VALUE in the future
                   $mathJs->set("SALARY", $ce->calculated_value);

                   foreach ($values as $value) {
                       $mathJs->setf("S_ACCOUNTANT_CONDITION", $value["condition"]);
                       $mathJs->setf("S_ACCOUNTANT_VALUE", $value["variable_value"]);
                       // result is pushed to queue
                       $mathJs->calc();
                       // $mathJs->clear();
                   }
               }

               $results = $mathJs->getResult();

               logger('r', [$results]);
               
               // Enable this to get debug input
               // if (config("app.debug")) {
               //     Storage::disk("local")->put(
               //         "payroll_accountant_" . time() . "_input.log",
               //         implode("\n", $mathJs->getLogs())
               //     );
               // }

               // CASE: một cột trong báo cáo kế toán, depend lên một cột khác cũng trong báo cáo
               $mathJs->start();
               $mathJs->formulas($formulas);

               // Loop same loop again, to get results
               $index = 0;
               for ($i = 0; $i < count($ces); $i++) {
                   foreach ($values as &$value) {
                       $result = $results[$index];
                       $calculatedValue = 0;
                       if (intval($result["S_ACCOUNTANT_CONDITION"]) == 1) {
                           if ($result["S_ACCOUNTANT_VALUE"] == "NaN") {
                               $calculatedValue = 0;
                           } else {
                               $calculatedValue =  floatval($result["S_ACCOUNTANT_VALUE"]);
                           }
                           $value['value']  += $calculatedValue;
                       }

                       if ($value["condition"] == "") {
                           // Tính lại khi tính lần 2
                           $mathJs->setf($value['variable_name'], $value['variable_value']);
                       } else {
                           // Set cứng giá trị để tính lần 2
                           $mathJs->set($value['variable_name'], $calculatedValue);
                       }
                       $index++;
                   }
                   $mathJs->calc();
                   $mathJs->clear();
               }

               // Enable this to get debug input
               // if (config("app.debug")) {
               //     Storage::disk("local")->put(
               //         "payroll_accountant_" . time() . "_input.log",
               //         implode("\n", $mathJs->getLogs())
               //     );
               // }

               // Assign kết quả tính lần 2
               $passTwoResults = $mathJs->getResult();

               foreach ($passTwoResults as $passTwoResult) {
                   foreach ($values as &$value) {
                       if (isset($passTwoResult[$value['variable_name']])) {
                           if ($passTwoResult[$value['variable_name']] == "NaN") {
                               $calculatedValue = 0;
                           } else {
                               $calculatedValue =  floatval($passTwoResult[$value['variable_name']]);
                           }
                           $value['value']  += $calculatedValue;
                       }
                   }
               }
           });

        // perset calculated value to CalculationSheet
        return $values;
    }
}
