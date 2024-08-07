<?php

namespace App\Console\Commands;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeeContract;
use Illuminate\Console\Command;

class TidyUpdateClientEmployeeContract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tidy:updateClientEmployeeContract {clientCode?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update client employee contract';

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
        $clientCode = $this->argument('clientCode');
        $query = ClientEmployee::select('*');

        if ($clientCode) {
            $query->whereHas('client', function ($subQuery) use ($clientCode) {
                $subQuery->where('code', $clientCode);
            });
        }

        $query->chunkById(100, function ($employees) {
            foreach ($employees as $clientEmployee) {
                $contracts = ClientEmployeeContract::where('client_employee_id', $clientEmployee->id)
                                                   ->orderBy('contract_signing_date', 'ASC')->get();

                foreach ($contracts as $contract) {
                    if ($contract) {
                        $contract_type = '';
                        switch ($contract->contract_type) {
                            case 'co-thoi-han-lan-1':
                            case 'co-thoi-han-lan-2':
                                $contract_type = 'chinhthuc';
                                break;
                            case 'khong-xac-dinh-thoi-han':
                                $contract_type = 'khongthoihan';
                                break;
                            case 'thuviec':
                                $contract_type = 'thuviec';
                                break;
                            default:
                                $contract_type = 'thoivu';
                                break;
                        }

                        $this->info('Process ... ['.$clientEmployee->code.']'.$clientEmployee->full_name);
                        $contract_signing_date = $contract->contract_signing_date ?: null;
                        if ($contract_type == 'thuviec') {
                            $contract_end_date = $contract->contract_end_date ?: null;
                            $clientEmployee->update([
                                // 'type_of_employment_contract' => $contract_type,
                                'probation_start_date' => $contract_signing_date,
                                'probation_end_date' => $contract_end_date,
                            ]);
                        } elseif ($contract_type == 'chinhthuc' || $contract_type == 'khongthoihan') {
                            $clientEmployee->update([
                                'type_of_employment_contract' => $contract_type,
                                'official_contract_signing_date' => $contract_signing_date,
                            ]);
                        }
                        $this->info('Updated ['.$clientEmployee->code.']'.$clientEmployee->full_name.':'
                            .$clientEmployee->type_of_employment_contract.'->'.$contract_type);
                    }
                }
            }
        }, 'id');
    }
}
