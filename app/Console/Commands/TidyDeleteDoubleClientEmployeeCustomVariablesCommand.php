<?php

namespace App\Console\Commands;

use App\Models\ClientEmployeeCustomVariable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TidyDeleteDoubleClientEmployeeCustomVariablesCommand extends Command
{
    protected $signature = 'tidy:deleteDoubleClientEmployeeCustomVariables {clientId?} {--dry-run}';

    protected $description = 'Remove double variables from clientEmployee variables';

    public function handle()
    {
        $dryRun = $this->option("dry-run");
        $clientId = $this->argument('clientId');

        $query = ClientEmployeeCustomVariable::query()
        ->select(['client_employee_id', 'variable_name'])
        ->selectRaw('count(`variable_name`) as `variable_names`')
        ->groupBy(['client_employee_id', 'variable_name']);

        if ($clientId) {
            $query->whereHas('client', function ($subQuery) use ($clientId) {
                $subQuery->where('id', $clientId);
            });
        }

        $query->chunk(100, function ($employeeCustomVariables) use ($dryRun) {

            foreach ($employeeCustomVariables as $employeeCustomVariable) {

                if($employeeCustomVariable->variable_names > 1){
                    $this->info("Not Valid ".$employeeCustomVariable->client_employee_id." ".$employeeCustomVariable->variable_name);

                    if (!$dryRun) {
                        $lastestVariable = ClientEmployeeCustomVariable::select('*')->where('client_employee_id', $employeeCustomVariable->client_employee_id)
                                                            ->where('variable_name', $employeeCustomVariable->variable_name)
                                                            ->orderBy('created_at', 'DESC')->limit(1)->first();

                        ClientEmployeeCustomVariable::select('*')->where('client_employee_id', $employeeCustomVariable->client_employee_id)
                                                            ->where('variable_name', $employeeCustomVariable->variable_name)
                                                            ->where('id', '!=', $lastestVariable->id)->delete();
                    }
                }else{
                    $this->info("Valid ".$employeeCustomVariable->client_employee_id." ".$employeeCustomVariable->variable_name);
                }
            }
        });
    }
}
