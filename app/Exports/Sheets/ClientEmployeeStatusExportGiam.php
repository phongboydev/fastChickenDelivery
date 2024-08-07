<?php
namespace App\Exports\Sheets;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

use Illuminate\Support\Carbon;


use App\Models\Client;
use App\Models\ClientEmployee;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ClientEmployeeStatusExportGiam implements FromView, WithTitle, WithEvents
{

    private $client_id = null;
    private $from_date = null;
    private $to_date   = null;

    private $total_list = 0;

    function __construct($clientId, $fromDate, $toDate)
    {
        $this->client_id = $clientId;
        $this->from_date = $fromDate;
        $this->to_date = $toDate;
    }

    public function view(): View
    {
        $client = Client::where('id', $this->client_id)->first();

        $today  = Carbon::now();

        $employees = ClientEmployee::select('full_name', 'sex', 'date_of_birth', 'type_of_employment_contract', 'education_level', 'position', 'official_contract_signing_date')
            ->where('client_id', $this->client_id)
            ->whereBetween('quitted_at', [$this->from_date, $this->to_date])->get();

        if( !empty($employees) ) {
            $this->total_list = count($employees);
        }

        return view('exports.client-employee-status-giam', [
            'now_day' => $today->format('d'),
            'now_month' => $today->format('m'),
            'now_year' => $today->format('Y'),
            'employees' => $employees
        ]);
    }

    public function title(): string
    {
        return 'Giam';
    }

    public function registerEvents(): array
    {

        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                    'font' => array(
                        'name'      =>  'Arial',
                        'size'      =>  11,
                    )
                ];

                $event->sheet->getDelegate()->getStyle('A1')->applyFromArray([
                    'font' => array(
                        'name'      =>  'Arial',
                        'size'      =>  11,
                    ),
                ]);

                $event->sheet->getDelegate()->getStyle('A3:R4')->getAlignment()->applyFromArray([
                    'vertical' => 'center',
                    'horizontal' => 'center'
                ]);

                $cellRange = 'A3:R' . (4 + $this->total_list);

                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(true);

                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);


            },
        ];
    }
}
