<?php
namespace App\Exports\Sheets;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class ClientHeadCountOverviewSheet implements FromView, WithTitle
{

    protected Collection $clients;

    public function __construct(Collection $clients)
    {
        $this->clients = $clients;
    }

    public function title(): string
    {
        return "Tổng hợp";
    }

    public function view(): View
    {
        return view(
            'exports.client-head-count-overview',
            [
                'clients' => $this->clients
            ]
        );
    }
}
