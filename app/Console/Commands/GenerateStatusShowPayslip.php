<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client;
use App\Models\CalculationSheet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateStatusShowPayslip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:status-show-payslip {fromDate} {toDate} {--clientCode=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate status show payslip of staff';

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
        $fromDate = $this->argument('fromDate');
        $toDate = $this->argument('toDate');
        $clientCode = $this->option('clientCode');

        try {

            DB::beginTransaction();

            //format date
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
            $toDate = Carbon::parse($toDate)->format('Y-m-d'); 
            $clients = new Client();

            // check from date less or same to dates
            if($toDate < $fromDate){
                $this->info('toDate >= fromDate');
                $this->info('Fail!');
                return false;
            }
            if($clientCode){
                //Get client bye client code
                $clients = $clients->where('code', $clientCode);
            }

            $clients= $clients->get();

            foreach($clients as $client){
                
                $CalculationSheets = CalculationSheet::where('client_id', $client->id)
                ->whereDate('created_at', '>=', $fromDate )
                ->whereDate('created_at', '<=', $toDate )
                ->get();                

                foreach($CalculationSheets as $CalculationSheet){
                    $calculationSheetClientEmployees = $CalculationSheet->calculationSheetClientEmployees;
                    if( count($calculationSheetClientEmployees) > 0){
                        $jsonEmployeeeIds = json_encode($calculationSheetClientEmployees->pluck('client_employee_id')->toArray());
                        $CalculationSheet->list_employee_notify_ids = $jsonEmployeeeIds;
                        $CalculationSheet->save();
                    }
                }
            }

            DB::commit();

            $this->info($fromDate);
            $this->info($toDate);
            $this->info($clientCode);
            //send output to the console
            $this->info('Success!');
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error($e->getMessage());
        }
        return 0;
    }
}
