<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use App\Models\ClientEmployee;
use App\Models\Client;

class ClientEmployeeExport implements FromView, WithTitle, WithEvents, ShouldAutoSize
{
    use Exportable;

    protected $client_id = null;
    protected $client = null;

    function __construct($clientId)
    {
        $this->client_id = $clientId;
    }

    public function view()
    : View
    {
        $client = Client::select('*')->where('id', $this->client_id)->first();

        $employees = ClientEmployee::select('*')
            ->where('client_id', '=', $this->client_id)
            ->with('user')
            ->orderBy('full_name', 'ASC')
            ->get();

        $this->total_list = count($employees);

        $this->client = $client;

        return view('exports.client-employee', [
            'client' => $client,
            'employees' => $employees,
        ]);
    }

    public function title()
    : string
    {
        return 'Employees';
    }

    public function registerEvents()
    : array
    {

        return [
            AfterSheet::class => function (AfterSheet $event) {

                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                    'font'    => array(
                        'name' => 'Arial',
                        'size' => 11,
                    )
                ];


                $event->sheet->getDelegate()->getStyle('A1')->applyFromArray([
                    'font' => array(
                        'name' => 'Arial',
                        'size' => 13,
                    ),
                ]);

                $columns = 53;

                $col = Coordinate::stringFromColumnIndex($columns);
                $event->sheet->getDelegate()->getStyle('A3:' . $col . '3')->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'center',
                ]);

                $event->sheet->getDelegate()->getStyle('A4:A' . (4 + $this->total_list))->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'center'
                ]);

                $cellRange = 'A3:' . $col . (3 + $this->total_list + 1);

                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->applyFromArray([
                    'vertical'   => 'center',
                    'horizontal' => 'right'
                ])->setWrapText(true);
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);

                $event->sheet->getDelegate()->getStyle('A3:' . $col . '4')->applyFromArray([
                    'alignment' => [
                        'horizontal' => 'center',
                    ],
                    'font' => array(
                        'name' => 'Arial',
                        'bold' => true
                    )
                ])->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('97bfe7');
            },
        ];
    }
}