<?php

namespace App\Exports;

use App\Exports\Sheets\ClientHeadCountHistorySheet;
use App\Exports\Sheets\ClientHeadCountOverviewSheet;
use App\Models\Client;
use App\Models\ClientHeadCountHistory;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class ClientHeadCountExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct()
    {

    }

    public function sheets(): array
    {
        $clients = Client::select("id", "company_name")->authUserAccessible()->with('clientWorkflowSetting:client_id,client_employee_limit')->get();
        $headCountHistories = ClientHeadCountHistory::authUserAccessible()->with('client:id,company_name')->orderByDesc('created_at')->get();
        return [
            new ClientHeadCountOverviewSheet($clients),
            new ClientHeadCountHistorySheet($headCountHistories)
        ];
    }
}
