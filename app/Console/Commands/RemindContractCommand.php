<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReminderContractEmail;
use App\Models\ClientEmployeeContract;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Notifications\ClientContractExpiryReminderNotification;
use App\Support\Constant;
use Carbon\Carbon;
use App\User;

class RemindContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remind:contract';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind contract to client';

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
        $this->line("Process remind contract");
        $clients = Client::whereHas('clientWorkflowSetting', function($clientWorkflowSetting) {
            return $clientWorkflowSetting->where("enable_contract_reminder", '>=', 1);
        })->with('clientWorkflowSetting')->get();
        foreach ($clients as $client) {
            $this->line("Process client..." . $client->code);
            $days = $client->clientWorkflowSetting->enable_contract_reminder * 30;
            $clientEmployeeContract = ClientEmployeeContract::join('client_employees', 'client_employee_contracts.client_employee_id','client_employees.id')
            ->select("client_employees.id", "client_employees.full_name", "client_employees.code", "client_employee_contracts.contract_end_date", "client_employee_contracts.reminder_date")
            ->where(function($query) use($days) {
                $query->where(function($sub) use($days) {
                    $sub->where('client_employee_contracts.contract_type', 'thuviec')
                        ->whereNull('client_employee_contracts.reminder_date')
                        ->where("contract_end_date", "<=", Carbon::now()->addDays($days)->format('Y-m-d'))
                        ->where("contract_end_date", ">=", date('Y-m-d'));

                })
                ->orWhere(function($sub) use($days) {
                    $sub->whereIn('client_employee_contracts.contract_type', ['co-thoi-han-lan-1', 'co-thoi-han-lan-2'])
                        ->whereNull('client_employee_contracts.reminder_date')
                        ->where("contract_end_date", "<=", Carbon::now()->addDays($days)->format('Y-m-d'))
                        ->where("contract_end_date", ">=", date('Y-m-d'));
                })
                ->orWhere('client_employee_contracts.reminder_date', date('Y-m-d'));
            })
            ->where("client_employees.client_id", $client->id)
            ->where("client_employees.status", "đang làm việc")
            ->whereNull("client_employees.deleted_at")
            ->get()
            ->toArray();
            $argsClientEmployee = [];
            foreach ($clientEmployeeContract as $item) {
                if ($item['reminder_date']) {
                    unset($item['id']);
                    $argsClientEmployee[] = $item;
                    $this->line("Found contract of [${item['code']}] [${item['full_name']}, remind on=${item['reminder_date']}");
                } else {
                    $contract = ClientEmployeeContract::where('client_employee_id', $item['id'])
                        ->where("contract_signing_date", ">=",  $item['contract_end_date'])
                        ->get();
                    if($contract->isEmpty()){
                        unset($item['id']);
                        $argsClientEmployee[] = $item;
                        $this->line("Found contract of [${item['code']}] [${item['full_name']}, end on=${item['contract_end_date']}");
                    }
                }
            }
            $clientEmployeeContract = $argsClientEmployee;
            if (count($clientEmployeeContract) > 0) {
                $managers = ClientEmployee::where(function ($query) {
                        return $query->where('role', Constant::ROLE_CLIENT_MANAGER)
                                    ->orWhereHas('user', function($query) {
                                        return $query->permission(Constant::PERMISSION_MANAGE_CONTRACT);
                                    });
                    })
                    ->where('client_id', $client->id)
                    ->where("status",'!=', Constant::CLIENT_EMPLOYEE_STATUS_QUIT)
                    ->get();
                $managers->each(function(ClientEmployee $clientEmployee) use ($clientEmployeeContract) {
                    $user = $clientEmployee->user;
                    if ($user) {
                        $this->line("Send email to -----------> " . $clientEmployee->full_name . " Code " . $clientEmployee->client->code);
                        $clientEmployee->user->notify(new ClientContractExpiryReminderNotification($clientEmployeeContract));
                    }
                });
            }
        }
        return Command::SUCCESS;
    }
}
