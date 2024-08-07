<?php

namespace App\Console\Commands;

use App\Models\ClientEmployeeLeaveManagement;
use App\Models\ClientEmployeeLeaveManagementByMonth;
use App\Support\WorktimeRegisterHelper;
use Illuminate\Console\Command;

class RefreshLeaveByMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:refreshLeaveByMonth {clientCode} {type} {sub_type} {year} {fromDate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh leave by month';

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
        $clientCode = $this->argument("clientCode");
        $type = $this->argument("type");
        $subType = $this->argument("sub_type");
        $year = $this->argument("year");
        $fromDate = $this->argument("fromDate");
        ClientEmployeeLeaveManagement::whereHas('leaveCategory', function ($query) use ($clientCode, $type, $subType, $year, $fromDate) {
            $query->where('type', $type)
                ->where('sub_type', $subType)
                ->where('year', $year)
                ->whereHas('client', function ($subQuery) use ($clientCode) {
                    $subQuery->where('code', $clientCode);
                });
        })
            ->whereHas('clientEmployeeLeaveManagementByMonth')
            ->chunkById(1000, function ($items) use ($type, $subType, $fromDate) {
                foreach ($items as $item) {
                    $clientEmployeeLeaveManagementByMonth = $item->clientEmployeeLeaveManagementByMonth()->orderBy('start_date')->get();
                    $remainMonth = $item->entitlement;
                    $countEntitlementUsed = 0;
                    foreach ($clientEmployeeLeaveManagementByMonth as $itemChild) {
                        if (strtotime($fromDate) < strtotime($itemChild->end_date)) {
                            $condition = [
                                'start' => $itemChild->start_date,
                                'end' => $itemChild->end_date,
                                'client_employee_id' => $item->client_employee_id,
                                'sub_type' => $type,
                                'category' => $subType,
                            ];
                            $countIsUsedLeave = WorktimeRegisterHelper::getCountIsUseLeaveRequest($condition);
                            // Update leave employee leave by months
                            $remainMonth -= $countIsUsedLeave;
                            $itemChild->entitlement_used = $countIsUsedLeave;
                            $itemChild->remaining_entitlement = $remainMonth;
                            $itemChild->save();
                            $countEntitlementUsed += $countIsUsedLeave;
                        } else {
                            $remainMonth -= $itemChild->entitlement_used;
                            $countEntitlementUsed += $itemChild->entitlement_used;
                        }
                    }
                    // Update detail employee leave
                    $item->entitlement_used = $countEntitlementUsed;
                    $item->remaining_entitlement = $item->entitlement - $countEntitlementUsed;
                    $item->saveQuietly();
                }
            });
        return 0;
    }
}
