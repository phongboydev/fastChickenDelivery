<?php

namespace App\GraphQL\Mutations;

use Carbon\Carbon;
use App\Models\DebitSetup;
use App\Models\Client;
use App\Models\CalculationSheet;
use App\Models\ClientEmployee;
use DB;

class DebitSetupMutator
{
    public function getClientsWithoutDebitSetup() {
        $debitSetupClientIDs = DebitSetup::all()->pluck("client_id")->toArray();
        
        return Client::whereNotIn('id', $debitSetupClientIDs)->get();
    }

    public function runDebitRequest($root, array $args) {
        $clientId = $args['client_id'];
        $client = Client::find($clientId);
        \Log::info("-----------------------------------------");
        \Log::info("Checking client ... $client->company_name");
        $debitSetup = $client->debitSetup;
        if (!$debitSetup) {
            return "Client does not setup debit";
        }

        $nextRunAt = date('Y-m-d');
        $nextRunAt = strtotime($nextRunAt);
        $now = strtotime(date('Y-m-d'));
                
        $currentDebitAmount = $debitSetup->current_debit_amount;
        $debitThresholdRequest = $debitSetup->debit_threshold_request;
        $cs = 'calculation_sheets';
        $csce = 'calculation_sheet_client_employees';
        $latestYear = CalculationSheet::query()
                            ->from('calculation_sheets')
                            ->where("client_id", $client->id)
                            ->max('year');
        $latestMonth = CalculationSheet::query()
                            ->from('calculation_sheets')
                            ->where('year', $latestYear)
                            ->where("client_id", $client->id)
                            ->max('month');

        $salaryAmountToPayQuery =  CalculationSheet::query()
                    // ->where('month', function($query) use ($client){
                    //     $query
                    //         ->selectRaw('max(month)')
                    //         ->from('calculation_sheets')
                    //         ->where('year', DB::raw("(select max(year) from calculation_sheets where client_id='$client->id')"));
                    // })
                    // ->where('year', function($query) use ($client){
                    //     $query
                    //         ->selectRaw('max(year)')
                    //         ->from('calculation_sheets')
                    //         ->where("client_id", $client->id);
                    // })
                    ->where('month', $latestMonth)
                    ->where('year', $latestYear)
                    ->where('status', 'client_approved')
                    ->where("$cs.client_id", $client->id)
                    ->join($csce, "$csce.calculation_sheet_id", "$cs.id");
        $salaryAmountToPay = $salaryAmountToPayQuery->sum("$csce.calculated_value");
        \Log::info("Current Debit Amount ----- $currentDebitAmount");
        \Log::info("Debit Threshold Request ----- $debitThresholdRequest");
        \Log::info("Salary amount need to pay: $salaryAmountToPay");

        $currentMonth = date('Y-m');
        $cutoffMonth = $debitSetup->cutoff_month ? $debitSetup->cutoff_month : '0';
        $compare = $currentDebitAmount - $salaryAmountToPay - $debitThresholdRequest;
        if ($compare < 0) {
            $debitAmount = $debitThresholdRequest + $salaryAmountToPay - $currentDebitAmount;
            $dueDateStr = "$currentMonth-$debitSetup->due_date";
            $dueDate = date('Y-m-d', strtotime("+$debitSetup->due_month months", strtotime($dueDateStr)));
            $cutoffDate = date('Y-m-d', strtotime("+$cutoffMonth months", strtotime("$currentMonth-01")));
            $debitRequestData = [
                'client_id' => $client->id,
                'due_date' => $dueDate,
                'debit_amount' => $debitAmount,
                'current_debit_amount' => $currentDebitAmount,
                'cutoff_date' => $cutoffDate

            ];
            $client->debitRequests()->create($debitRequestData);
            $debitSetup->next_run_at = date('Y-m-d', strtotime('+1 months', $nextRunAt));
        } else {
            $nextRunAt = "$currentMonth-$debitSetup->debit_date";
            $debitSetup->next_run_at = date('Y-m-d', strtotime("+1 months", strtotime($nextRunAt)));
        }
                        
        $debitSetup->last_run_at = date('Y-m-d');
        $debitSetup->save();

        \Log::info("Last run at ----- $debitSetup->last_run_at");
        \Log::info("Next run at ----- $debitSetup->next_run_at");
        \Log::info("-----------------------------------------");

        $employees = $salaryAmountToPayQuery
                        ->select($cs.".month", $cs . ".year", 'client_employee_id', 'calculated_value')
                        ->get();
        foreach ($employees as $employee) {
            $employee->full_name = ClientEmployee::find($employee->client_employee_id)->full_name ?? "";
        }

        $payload = array(
            'client_id' => $client->id,
            'client' => $client->company_name,
            'current_debit_amount' => $currentDebitAmount,
            'debit_threshold_request' => $debitThresholdRequest,
            'salary_amount_need_to_pay' => $salaryAmountToPay,
            'employees' => $employees,
            'month' => $latestMonth,
            'year' => $latestYear
        );

        return json_encode($payload);
    }
}
