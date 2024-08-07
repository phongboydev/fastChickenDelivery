<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorktimeRegisterExport implements FromView, WithStyles
{
    protected $data;
    protected $fromDate;
    protected $toDate;
    protected $status;
    protected $viewTemplate;
    protected $standardWorkHours;
    protected $wt_category;
    protected $timezone_name;
    protected $listStatus = array(
        'pending' => 'model.timesheets.state.pending',
        'approved' => 'approve_state.approved',
        'canceled_approved' => 'model.clients.canceled',
        'canceled' => 'model.clients.rejected',
        'await_cancel_approved' => 'model.clients.cancel_pending'
    );
    protected $listType = array(
        'leave_early' => 'model.worktime_register.early_late_request.type.leave_early',
        'authorized_leave' => 'model.clients.paid_leave',
        'unauthorized_leave' => 'leave_request.unauthorized.unauthorized_leave',
        'outside_working' => 'leave_request.outside.outside_working',
        'wfh' => 'model.worktime_register.congtac_request.type.wfh',
        'ot_holiday' => 'model.worktime_register.overtime_request.type.ot_holiday',
        'ot_weekday' => 'model.worktime_register.overtime_request.type.ot_weekday',
        'ot_weekend' => 'model.worktime_register.overtime_request.type.ot_weekend',
        'ot_makeup' => 'model.worktime_register.makeup_request.type.ot_makeup',
        'assigned_ot_holiday' => 'type.assigned_overtime_on_holidays',
        'assigned_ot_weekday' => 'type.assigned_overtime_on_weekdays',
        'assigned_ot_weekend' => 'type.assigned_overtime_on_weekends',
        'assigned_ot_makeup' => 'model.worktime_register.makeup_request.type.assign_ot_makeup',
        'business_trip' => 'model.timesheets.work_status.di_cong_tac',
        'other' => 'leave_request.other.other',
        'road' => 'leave_request.business.road',
        'airline' => 'leave_request.business.airline',
    );

    public function __construct($params = [])
    {
        $this->data = $params['data'];
        $this->fromDate = isset($params['from_date']) ? date('d/m/Y', strtotime($params['from_date'])) : "##########";
        $this->toDate = isset($params['to_date']) ? date('d/m/Y', strtotime($params['to_date'])) : "##########";
        $this->standardWorkHours = $params['standard_work_hours'];
        $this->status = $params['status'];
        $this->timezone_name = $params['timezone_name'];
        $this->wt_category = $params['type'] == 'leave_request' ? $params['wt_category'] : NULL;
        $type = $params['type'];
        switch ($type) {
            case 'leave_request':
                $this->viewTemplate = 'exports.work-time-register-leave';
                break;
            case 'ot_and_makeup':
            case 'overtime_request':
            case 'makeup_request':
                $this->viewTemplate = 'exports.work-time-register-ot';
                break;
            case 'congtac_request':
                $this->viewTemplate = 'exports.work-time-register-congtac';
                break;
            default:
                break;
        }
    }

    public function view(): View
    {
        return view($this->viewTemplate, [
            'data' => $this->data,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'status' => $this->status,
            'standardWorkHours' => $this->standardWorkHours,
            'listStatus' => $this->listStatus,
            'listType' => $this->listType,
            'timezone_name' => $this->timezone_name,
            'wt_category' => $this->wt_category
        ]);
    }

    public function styles(Worksheet $sheet)
    {

        $sheet->getStyle('B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
            4 => [
                'font' => ['bold' => true],
            ]
        ];
    }
}
