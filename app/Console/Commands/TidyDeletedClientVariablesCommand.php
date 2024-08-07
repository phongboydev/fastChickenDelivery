<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientCustomVariable;
use App\Models\ClientEmployee;
use App\Models\ClientEmployeeCustomVariable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class TidyDeletedClientVariablesCommand extends Command
{

    protected $signature = 'tidy:clientVariables {clientId?} {--dry-run}';

    protected $description = 'Remove deleted variables from clientEmployee variables';

    public function handle()
    {
        $dryRun = $this->option("dry-run");
        $clientId = $this->argument('clientId');
        $query = Client::query();
        if ($clientId) {
            $query->where("id", $clientId);
        }
        $query->chunk(10, function ($clients) use ($dryRun) {
            /** @var Client $client */
            foreach ($clients as $client) {
                $this->info("Processing ".$client->code." ".$client->company_name);
                $vars = ClientCustomVariable::query()
                                            ->select("variable_name")
                                            ->where("client_id", $client->id)
                                            ->get();
                ClientEmployee::query()
                              ->where("client_id", $client->id)
                              ->chunk(100, function (Collection $employees) use ($dryRun, $vars) {
                                  /** @var ClientEmployee[] $employees */
                                  foreach ($employees as $employee) {
                                      $count = ClientEmployeeCustomVariable::query()
                                                                           ->where("client_employee_id", $employee->id)
                                                                           ->whereNotIn("variable_name",
                                                                               $vars->pluck("variable_name"))
                                                                           ->count();

                                      if ($count) {
                                          $this->info("Processing ".$employee->code." ".$employee->full_name."... Found $count.");
                                      }

                                      if (!$dryRun) {
                                          ClientEmployeeCustomVariable::query()
                                                                      ->where("client_employee_id", $employee->id)
                                                                      ->whereNotIn("variable_name",
                                                                          $vars->pluck("variable_name"))
                                                                      ->delete();
                                      }
                                  }
                              });
            }
        });
    }
}
