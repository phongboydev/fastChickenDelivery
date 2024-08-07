<?php

namespace App\Observers;

use App\Models\CalculationSheetClientEmployee;
use App\Models\CalculationSheetVariable;
use FormulaParser\FormulaParser;

class CalculationSheetClientEmployeeObserver
{

    /**
     * Handle the calculation sheet client employee "created" event.
     *
     * @param CalculationSheetClientEmployee $calculationSheet
     *
     * @return void
     */
    public function created(CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {

    }

    /**
     * Handle the calculation sheet client employee "updated" event.
     *
     * @param CalculationSheetClientEmployee $calculationSheetClientEmployee
     *
     * @return void
     */
    public function updated(CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {

    }

    /**
     * Handle the calculation sheet client employee "deleted" event.
     *
     * @param CalculationSheetClientEmployee $calculationSheetClientEmployee
     *
     * @return void
     */
    public function deleted(CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        CalculationSheetVariable::where([
            'calculation_sheet_id' => $calculationSheetClientEmployee->calculation_sheet_id,
            'client_employee_id' => $calculationSheetClientEmployee->client_employee_id,
        ])->delete();
    }

    /**
     * Handle the calculation sheet client employee "restored" event.
     *
     * @param CalculationSheetClientEmployee $calculationSheetClientEmployee
     *
     * @return void
     */
    public function restored(CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        //
    }

    /**
     * Handle the calculation sheet client employee "force deleted" event.
     *
     * @param CalculationSheetClientEmployee $calculationSheetClientEmployee
     *
     * @return void
     */
    public function forceDeleted(CalculationSheetClientEmployee $calculationSheetClientEmployee)
    {
        //
    }

}
