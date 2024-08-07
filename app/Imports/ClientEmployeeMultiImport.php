<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ClientEmployeeMultiImport implements WithMultipleSheets
{

    private $start_row;
    private $client_id;

    public function __construct($type, $client_id)
    {
        $this->client_id = $client_id;

        switch ($type) {
            case 'client_employee':
                $this->start_row = 4;
                break;

            default:
                $this->start_row = 3;
                break;
        }
    }

    public function sheets(): array
    {
        return [
            0 => new ClientEmployeeImport($this->start_row, $this->client_id)
        ];
    }
}
