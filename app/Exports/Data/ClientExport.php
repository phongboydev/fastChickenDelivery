<?php

namespace App\Exports\Data;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;

use App\Models\Client;

class ClientExport implements FromQuery, WithHeadings
{
    use Exportable, ExportHeadingsTrait;

    public function query()
    {
        return Client::query();
    }
}
