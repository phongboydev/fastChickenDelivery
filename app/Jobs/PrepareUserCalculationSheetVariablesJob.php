<?php

namespace App\Jobs;

use App\Events\CalculationSheetReadyEvent;
use App\Models\CalculationSheet;
use App\Models\CalculationSheetClientEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PrepareUserCalculationSheetVariablesJob implements ShouldQueue
{

    protected CalculationSheet $sheet;
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    /**
     * PrepareUserCalculationSheetVariablesJob constructor.
     *
     * @param CalculationSheet $sheet
     */
    public function __construct(CalculationSheet $sheet) { $this->sheet = $sheet; }

    public function handle()
    {
        $calculationSheet = $this->sheet;

        // Batch variable copy
        DB::statement("
            INSERT INTO calculation_sheet_variables (id, calculation_sheet_id, client_employee_id, readable_name, variable_name, variable_value)
            SELECT UUID(), :calculationSheetId, a.client_employee_id, a.readable_name, a.variable_name, a.variable_value
            FROM client_employee_custom_variables a LEFT JOIN client_employees b ON a.client_employee_id = b.id
            WHERE
              b.client_id = :clientId AND b.id IN (
                SELECT client_employee_id
                FROM calculation_sheet_template_assignments
                WHERE template_id = :templateId
              )
            AND b.deleted_at IS NULL
        ", [
            "calculationSheetId" => $calculationSheet->id,
            "clientId" => $calculationSheet->client_id,
            "templateId" => $calculationSheet->calculation_sheet_template_id,
        ]);
        DB::statement("
            INSERT INTO calculation_sheet_variables (id, calculation_sheet_id, client_employee_id, readable_name, variable_name, variable_value)
            SELECT UUID(), :calculationSheetId, b.client_employee_id AS client_employee_id, a.readable_name, a.variable_name, a.variable_value
            FROM client_custom_variables a, (
                SELECT client_employee_id
                FROM calculation_sheet_template_assignments
                WHERE template_id = :templateId
            ) b
            WHERE a.client_id = :clientId
            AND a.variable_name NOT IN (SELECT variable_name FROM calculation_sheet_variables WHERE client_employee_id = b.client_employee_id AND calculation_sheet_id = :calculationSheetId2)
        ", [
            "calculationSheetId" => $calculationSheet->id,
            "clientId" => $calculationSheet->client_id,
            "templateId" => $calculationSheet->calculation_sheet_template_id,
            "calculationSheetId2" => $calculationSheet->id,
        ]);
        CalculationSheetClientEmployee::where("calculation_sheet_id", $calculationSheet->id)
                                      ->update([
                                          "user_vars_ready" => 1,
                                      ]);

        $hasNotReadyRecord = CalculationSheetClientEmployee::where("calculation_sheet_id", $calculationSheet->id)
                                                           ->notReady()
                                                           ->exists();
        if (! $hasNotReadyRecord) {
            event(new CalculationSheetReadyEvent($calculationSheet));
        }
    }
}
