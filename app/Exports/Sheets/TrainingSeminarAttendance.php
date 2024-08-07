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

class TrainingSeminarAttendance implements Responsable, FromView, WithStyles, WithEvents, WithDefaultStyles, WithColumnWidths, WithTitle
{
    use Exportable;

    protected $data;
    protected $total_rows;

    public function __construct($params = [])
    {
        $this->data = $params['data'];
        $this->total_rows = $params['total_rows_sheet_2'] + 2;
        $this->range_cell_border = 'A1:F' . $this->total_rows;
        $this->type = $params['type'];
    }

    public function title(): string
    {
        return $this->type === 'USER' ? 'Thông tin điểm danh' : 'Danh sách học viên';
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
        $sheet->getStyle($this->range_cell_border)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setWrapText(true)->setVertical('center');
        $applyFromArray = ['fillType' => 'solid', 'rotation' => 0, 'color' => ['rgb' => '6FA8DC']];
        $sheet->getStyle('A1:F2')->getFill()->applyFromArray($applyFromArray);
        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }

    public function columnWidths(): array
    {
        return [
            'B' => '15',
            'C' => '30',
            'D' => '40',
            'E' => '25',
            'F' => '40'
        ];
    }

    public function registerEvents(): array
    {
        return [];
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
        return view('exports.training-seminar-attendance', [
            'data' => $this->data,
        ]);
    }
}
