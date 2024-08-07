<?php

namespace App\Exports;

use App\Models\DebitRequest;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DebitRequestExport implements FromView, WithStyles
{
    protected $id;

    public function __construct($id) {
        $this->id = $id;
    }

    public function view(): View
    {
        $debitRequest = DebitRequest::find($this->id);
        $client = $debitRequest->client;
        $currentDebitAmount = number_format($debitRequest->current_debit_amount, 2, '.', ',') . " VNĐ"; 
        $debitAmount = number_format($debitRequest->adjusted_debit_amount ?? $debitRequest->debit_amount, 2, '.', ',') . " VNĐ"; 
        $data = array(
            'companyName' => $client->company_name,
            'address' => $client->address,
            'receiverName' => 'Nguyễn Văn A',
            'currentDebitAmount' => $currentDebitAmount,
            'debitAmount' => $debitAmount,
            'dueDate' => date("d/m/Y", strtotime($debitRequest->due_date)),
            'bankName' => '',
            'branchName' => '',
            'accountNumber' => '',
            'accountName' => ''

        );

        return view('exports.debit-request-excel', [
            'data' => $data,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $bgColorYellow = array(
            'fillType' => 'solid',
            'color' => ['rgb' => 'FFFF00'],
        );
        $bgColorGreen = array(
            'fillType' => 'solid',
            'color' => ['rgb' => '00FF00'],
        );
        $bgColorWhite = array(
            'fillType' => 'solid',
            'color' => ['rgb' => 'FFFFFF'],
        );
        $sheet->getStyle('B1:B3')
              ->getFill()
              ->applyFromArray($bgColorYellow);
        $sheet->getStyle('B7')
              ->getFill()
              ->applyFromArray($bgColorYellow);
        $sheet->getStyle('B8')
              ->getFill()
              ->applyFromArray($bgColorGreen);
        $sheet->getStyle('B10')
              ->getFill()
              ->applyFromArray($bgColorGreen);

        $sheet->getStyle('B13:B16')
              ->getFill()
              ->applyFromArray($bgColorYellow);
        $sheet->getStyle('A1:A16')
              ->getFill()
              ->applyFromArray($bgColorWhite);
        $outlineBorder = array(
                'borders' => [
                    'outline' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK,
                        'color' => ['argb' => '000000'],
                    ],
                ],
            );
        $sheet->getStyle("A1:B16")->applyFromArray($outlineBorder);
    }
}
