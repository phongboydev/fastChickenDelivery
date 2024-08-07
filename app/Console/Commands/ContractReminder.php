<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientEmployeeContract;
use App\Notifications\ClientContractExpiryReminderNotification;
use App\Support\Constant;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ContractReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:contractreminder {--d|dry-run} {--c|client-code= : Code of client}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Client Contract reminder';

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
        $clientCode = $this->option("client-code");
        $dryRun = $this->option('dry-run');

        $clientQuery = Client::whereHas('clientWorkflowSetting', function($clientWorkflowSetting) {
            return $clientWorkflowSetting->where("enable_contract_reminder", ">=", 1);
        })->with('clientWorkflowSetting');
        if ($clientCode) {
            $clientQuery->where('code', $clientCode);
        }
        $clients = $clientQuery->get();
        foreach ($clients as $client) {
            $this->line("Process Client ... " . $client->code);
            $days = $client->clientWorkflowSetting->enable_contract_reminder * 30;
            $clientEmployeeContract = ClientEmployeeContract::join('client_employees', 'client_employee_contracts.client_employee_id','client_employees.id')
                ->select("client_employees.full_name", "client_employees.code", "client_employee_contracts.contract_end_date")
                ->where("client_employees.client_id", $client->id)
                ->where("contract_end_date", ">", Carbon::today())->where("contract_end_date", "<", Carbon::now()->addDays($days))
                ->get()
                ->toArray();
            foreach ($clientEmployeeContract as $item) {
                $this->line("Found contract of [${item['code']}] ${item['full_name']}, end on=${item['contract_end_date']}");
            }
            if (count($clientEmployeeContract) > 0) {
                $managers = User::whereHas("clientEmployee", function($manager) use($client){
                    return $manager->where(function ($query) {
                                $query->where('role', Constant::ROLE_CLIENT_MANAGER)
                                    ->orWhere('role',Constant::ROLE_CLIENT_HR);
                            })->where("client_id", $client->id);
                })->get();
                $managers->each(function(User $manager) use ($dryRun, $clientEmployeeContract) {
                    if (!$dryRun) {
                        $this->line('Send notification to ' . $manager->name);
                        $manager->notify(new ClientContractExpiryReminderNotification($clientEmployeeContract));
                    }
                });
            }
        }
    }
}
