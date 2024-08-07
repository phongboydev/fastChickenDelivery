<?php
namespace App\Exports\Sheets;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class ClientHeadCountHistorySheet implements FromView, WithTitle
{

    protected Collection $headCountHistories;

    public function __construct(Collection $headCountHistories)
    {
        $this->headCountHistories = $headCountHistories;
    }

    public function title(): string
    {
        return "Phát sinh";
    }

    public function view(): View
    {
        return view(
            'exports.client-head-count-history',
            [
                'headCountHistories' => $this->headCountHistories
            ]
        );
    }
}
