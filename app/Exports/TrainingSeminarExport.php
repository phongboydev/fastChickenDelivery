<?php

namespace App\Exports;

use App\Models\TrainingSeminar;
use App\Models\ClientDepartment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithBackgroundColor;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;

class TrainingSeminarExport implements Responsable, FromView, WithStyles, WithBackgroundColor, WithEvents, WithDefaultStyles, WithColumnWidths, WithTitle
{
    use Exportable;

    protected $data;
    protected $total_rows;
    protected $type;
    protected $fromDate;
    protected $toDate;

    public function __construct($params = [])
    {
        $this->data = $params['data'];
        $this->type = $params['type'];
        $this->fromDate = isset($params['fromDate']) ? date('d/m/Y', strtotime($params['fromDate'])) : "##########";
        $this->toDate = isset($params['toDate']) ? date('d/m/Y', strtotime($params['toDate'])) : "##########";
        $this->total_rows = $params['total_rows'] + 4;
        $this->rows_hide = $this->total_rows + 1;
        if ($this->type === 'USER') {
            $this->range_cell = 'A1:H4';
            $this->range_cell_border = 'A1:H' . $this->total_rows;
        } else {
            $this->range_cell = 'A1:I4';
            $this->range_cell_border = 'A1:I' . $this->total_rows;
        }
    }

    public function backgroundColor()
    {
    }

    public function title(): string
    {
        return $this->type;
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->type === 'USER') {
            $sheet->getStyle('F3')->getAlignment()->setWrapText(true)->setVertical('center');
        } else {
            $sheet->getStyle('F3:G3')->getAlignment()->setWrapText(true)->setVertical('center');
        }
        $sheet->getStyle($this->range_cell)->getFont()->setBold(true);
        $sheet->getStyle($this->range_cell)->getFill()->applyFromArray(['fillType' => 'solid', 'rotation' => 0, 'color' => ['rgb' => '6FA8DC']]);
        $sheet->getStyle($this->range_cell_border)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000']
                ],
            ],
        ]);
        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
    }

    public function columnWidths(): array
    {
        $data = [
            'B' => 35,
            'C' => 18,
            'D' => 18,
            'E' => 15,
            'F' => 11
        ];

        if ($this->type === 'USER') {
            $data['G'] = 40;
            $data['H'] = 18;
        } else {
            $data['G'] = 11;
            $data['H'] = 40;
            $data['I'] = 18;
        }

        return $data;
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_DATE_DATETIME,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle($this->range_cell_border)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
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
        return view('exports.training-seminar', [
            'data' => $this->data,
            'type' => $this->type,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate
        ]);
    }
}
