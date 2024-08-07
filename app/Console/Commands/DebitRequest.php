<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CalculationSheet;
use App\Models\Client;
use App\Models\DebitSetup;
use App\Models\DebitRequest as DebitRequestModel;
use DB;

class DebitRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debit:request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check debit request';

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
        // DebitSetup::truncate(); // TODO
        // DebitRequestModel::truncate(); // TODO
        \Log::info("Running debit request ------------- " . date('d/m/Y H:i'));
        $cs = 'calculation_sheets';
        $csce = 'calculation_sheet_client_employees';
        // $debitSetupData = array(
        //     [
        //         'debit_date' => 2,
        //         'due_date' => 5,
        //         'due_month' => 1,
        //         'cutoff_date' => 15,
        //         // 'cutoff_month' => 1,
        //         'current_debit_amount' => 5000000,
        //         // 'salary_amount_need_to_pay' => 8,
        //         'debit_threshold_request' => 5000000,
        //         // 'debit_threshold_payment' => 0,
        //         'next_run_at' => date('Y-m-d') 
        //     ],
        //     [
        //         'debit_date' => 2,
        //         'due_date' => 5,
        //         'due_month' => 1,
        //         'cutoff_date' => 15,
        //         'current_debit_amount' => 5000000,
        //         'debit_threshold_request' => 5000000,
        //         'next_run_at' => date('Y-m-d') 
        //     ]
        // );
        $debitSetupData = [
            'debit_date' => 2,
            'due_date' => 5,
            'due_month' => 1,
            'cutoff_date' => 15,
            'current_debit_amount' => 5000000,
            'debit_threshold_request' => 5000000,
            'next_run_at' => date('Y-m-d') 
        ];
        Client::chunk(100, function($clients) use ($cs, $csce, $debitSetupData) {
            // $debitSetupData = [
            //     'debit_date' => 2,
            //     'due_date' => 5,
            //     'due_month' => 1,
            //     'cutoff_date' => 10,
            //     'cutoff_month' => 1,
            //     'current_debit_amount' => 5,
            //     'salary_amount_need_to_pay' => 8,
            //     'debit_threshold_request' => 7,
            //     'debit_threshold_payment' => 0,
            //     'next_run_at' => date('Y-m-d') 
            // ];
            foreach ($clients as $key => $client) {
                // $client->debitSetup()->create($debitSetupData);
                $this->info("-----------------------------------------");
                $this->info("Checking client ... $client->company_name");
                \Log::info("-----------------------------------------");
                \Log::info("Checking client ... $client->company_name");
                $debitSetup = $client->debitSetup;
                if (!$debitSetup) {
                    if (isset($debitSetupData)) {
                        $this->info("Setting debit");
                        $debitSetup = $client->debitSetup()->create($debitSetupData);
                    } else {
                        $this->info("Do not setup debit");
                        continue;
                    }
                }

                $nextRunAt = $debitSetup->next_run_at;
                if (!$nextRunAt) {
                    $this->info("Do not setup next run at");
                    continue;
                }
                $nextRunAt = date('Y-m-d', strtotime($nextRunAt));
                $nextRunAt = strtotime($nextRunAt);
                $now = strtotime(date('Y-m-d'));
                
                if ($now - $nextRunAt == 0) { // If Next run at is today
                    $currentDebitAmount = $debitSetup->current_debit_amount;
                    $debitThresholdRequest = $debitSetup->debit_threshold_request;
                    $latestYear = CalculationSheet::query()
                                        ->from('calculation_sheets')
                                        ->where("client_id", $client->id)
                                        ->max('year');
                    $latestMonth = CalculationSheet::query()
                                        ->from('calculation_sheets')
                                        ->where('year', $latestYear)
                                        ->where("client_id", $client->id)
                                        ->max('month');
                    $salaryAmountToPay =  CalculationSheet::query()
                                ->where('month', $latestMonth)
                                ->where('year', $latestYear)
                                ->where('status', 'client_approved')
                                ->where("$cs.client_id", $client->id)
                                ->join($csce, "$csce.calculation_sheet_id", "$cs.id")
                                ->sum("$csce.calculated_value");
                    $this->info("Current Debit Amount ----- $currentDebitAmount");
                    $this->info("Debit Threshold Request ----- $debitThresholdRequest");
                    $this->info("Salary amount need to pay: $salaryAmountToPay");

                    \Log::info("Current Debit Amount ----- $currentDebitAmount");
                    \Log::info("Debit Threshold Request ----- $debitThresholdRequest");
                    \Log::info("Salary amount need to pay: $salaryAmountToPay");

                    $currentMonth = date('Y-m');
                    $cutoffMonth = $debitSetup->cutoff_month ? $debitSetup->cutoff_month : '0';
                    $compare = $currentDebitAmount - $salaryAmountToPay - $debitThresholdRequest;
                    if ($compare < 0) {
                        $debitAmount = $debitThresholdRequest + $salaryAmountToPay - $currentDebitAmount;
                        $this->info("Debit amount: $debitAmount");
                        $dueDateStr = "$currentMonth-$debitSetup->due_date";
                        $dueDate = date('Y-m-d', strtotime("+$debitSetup->due_month months", strtotime($dueDateStr)));
                        $cutoffDate = date('Y-m-d', strtotime("+$cutoffMonth months", strtotime("$currentMonth-01")));
                        $this->info('Due date ' . $dueDate);
                        $this->info('Cutoff date ' . $cutoffDate);
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
                        
                    $this->info("Last run at ----- $debitSetup->last_run_at");
                    $this->info("Next run at ----- $debitSetup->next_run_at");
                    $this->info("-----------------------------------------");

                    \Log::info("Last run at ----- $debitSetup->last_run_at");
                    \Log::info("Next run at ----- $debitSetup->next_run_at");
                    \Log::info("-----------------------------------------");
                }
            }
        });
        return Command::SUCCESS;
    }
}
