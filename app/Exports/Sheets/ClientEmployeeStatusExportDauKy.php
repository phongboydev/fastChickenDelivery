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

class ClientEmployeeStatusExportDauKy implements FromView, WithTitle, WithEvents
{

    private $client_id = null;
    private $from_date = null;
    private $to_date   = null;

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

        $company_name                   = $client->company_name ? $client->company_name : '......................';
        $address                        = $client->address ? $client->address : '......................';
        $company_contact_phone          = $client->company_contact_phone ? $client->company_contact_phone : '......................';
        $company_contact_email          = $client->company_contact_email ? $client->company_contact_email : '......................';
        $company_contact_fax            = $client->company_contact_fax ? $client->company_contact_fax : '......................';
        $company_license_at             = $client->company_license_at ? $client->company_license_at : '......................';

        $report = ClientEmployee::selectRaw('
            COUNT(id) AS tong_so,
            COUNT(IF(sex = \'female\', 1, NULL)) AS tong_so_ld_nu,
            COUNT(IF(education_level = \'Đại học trở lên\', 1, NULL)) AS education_level_1,
            COUNT(IF(education_level = \'Cao đẳng/Cao đẳng nghề\', 1, NULL)) AS education_level_2,
            COUNT(IF(education_level = \'Trung cấp/Trung cấp nghề\', 1, NULL)) AS education_level_3,
            COUNT(IF(education_level = \'Sơ cấp nghề\', 1, NULL)) AS education_level_4,
            COUNT(IF(education_level = \'daỵ nghề thường xuyên\', 1, NULL)) AS education_level_5,
            COUNT(IF(education_level = \'chưa qua đào tạo\', 1, NULL)) AS education_level_6,
            COUNT(IF(type_of_employment_contract = \'khongthoihan\', 1, NULL)) AS contract_type_1,
            COUNT(IF(type_of_employment_contract = \'chinhthuc\', 1, NULL)) AS contract_type_2,
            COUNT(IF(type_of_employment_contract = \'thoivu\', 1, NULL)) AS contract_type_3')
            ->where('client_id', $this->client_id)
            ->where('status', '!=', 'nghỉ việc')
            ->where('official_contract_signing_date', '<', $this->from_date)->first();


        return view('exports.client-employee-status-dau-ky', [
            'now_day' => $today->format('d'),
            'now_month' => $today->format('m'),
            'now_year' => $today->format('Y'),
            'company_name' => $company_name,
            'address' => $address,
            'company_contact_phone' => $company_contact_phone,
            'company_contact_fax' => $company_contact_fax,
            'company_contact_email' => $company_contact_email,
            'company_license_at' => $company_license_at,
            'report' => $report
        ]);
    }

    public function title(): string
    {
        return 'DauKy';
    }

    public function registerEvents(): array
    {

        return [
            AfterSheet::class    => function (AfterSheet $event) {

                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],

                ];

                $event->sheet->getDelegate()->getStyle('A1:L18')->applyFromArray([
                    'font' => array(
                        'name'      =>  'Arial',
                        'size'      =>  11,
                    ),
                ]);

                $cellRange = 'A16:L18';

                $event->sheet->getDelegate()->getStyle('A16:L17')->getAlignment()->applyFromArray([
                    'vertical' => 'center',
                    'horizontal' => 'center'
                ]);

                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(true);
                $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray($styleArray);
            },
        ];
    }
}
