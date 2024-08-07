<?php

namespace App\Exports\Sheets;

use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;

class TrainingSeminarInfo implements Responsable, FromView, WithStyles, WithEvents, WithDefaultStyles, WithColumnWidths, WithTitle
{
    use Exportable;

    protected $data;
    protected $total_rows;

    public function __construct($params = [])
    {
        $this->data = $params['data'];
        $this->total_rows = $params['total_rows_sheet_1'] + 7;
        $this->range_cell_border = 'A1:F' . $this->total_rows;
    }

    public function title(): string
    {
        return 'Thông tin đào tạo';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle($this->range_cell_border)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000']
                ],
            ],
        ]);

        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A6')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A5')->getAlignment()->setWrapText(true)->setVertical('center');

        $applyFromArray = ['fillType' => 'solid', 'rotation' => 0, 'color' => ['rgb' => '6FA8DC']];

        $sheet->getStyle('A1:F1')->getFill()->applyFromArray($applyFromArray);
        $sheet->getStyle('A2:A4')->getFill()->applyFromArray($applyFromArray);
        $sheet->getStyle('D3')->getFill()->applyFromArray($applyFromArray);
        $sheet->getStyle('A6:F7')->getFill()->applyFromArray($applyFromArray);

        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A2:A4')->getFont()->setBold(true);
        $sheet->getStyle('D3')->getFont()->setBold(true);
        $sheet->getStyle('A6:F7')->getFont()->setBold(true);
    }

    public function columnWidths(): array
    {
        return [
            'B' => '18',
            'C' => '18',
            'F' => '18'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $event->sheet->getRowDimension('5')->setRowHeight(100, 'pt');
            },
        ];
    }

    public function defaultStyles(Style $defaultStyle)
    {
        return [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID
            ],
        ];
    }

    public function view(): View
    {
        return view('exports.training-seminar-detail', [
            'data' => $this->data,
        ]);
    }
}
