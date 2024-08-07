<?php

namespace App\Console\Commands;

use App\Models\CalculationSheetVariable;
use Illuminate\Console\Command;

class TidyRemoveDoubleClientCustomVariable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:removeDoubleClientCustomVariable {calculation_sheet_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RemoveDoubleClientCustomVariable';

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
     * @return mixed
     */
    public function handle()
    {

        $calculationSheetId = $this->argument("calculation_sheet_id");

        $calculationSheetVariables = CalculationSheetVariable::selectRaw('variable_name, client_employee_id')
            ->groupBy(['variable_name', 'client_employee_id'])
            ->havingRaw('COUNT(id) > 1')
            ->where('calculation_sheet_id', $calculationSheetId)->get();

        if($calculationSheetVariables->isNotEmpty()){

            foreach($calculationSheetVariables as $calculationSheetVariable) {

              $doubleItems = CalculationSheetVariable::where('calculation_sheet_id', $calculationSheetId)
                                                      ->where('variable_name', $calculationSheetVariable->variable_name)
                                                      ->where('client_employee_id', $calculationSheetVariable->client_employee_id)->get();
              if($doubleItems->count() > 1) {
                $remainItem = $doubleItems->first();

                CalculationSheetVariable::where('calculation_sheet_id', $calculationSheetId)
                                                      ->where('variable_name', $calculationSheetVariable->variable_name)
                                                      ->where('client_employee_id', $calculationSheetVariable->client_employee_id)
                                                      ->where('id', '!=', $remainItem->id)->delete();
              }
            }
        }

    }
}
