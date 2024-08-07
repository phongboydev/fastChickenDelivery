<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Approve;
use App\Models\Supplier;
use App\Models\Bank;
use App\Models\PaymentRequestAmount;

class PaymentRequestExport implements FromView, WithStyles
{
    protected $data;
    protected $fromDate;
    protected $toDate;
    protected $timezone_name;

    // protected $listStatus = array(
    //     'pending' => 'Chờ duyệt',
    //     'approved' => 'Đã duyệt',
    //     'processing' => 'Chờ duyệt',
    //     'declined' => 'Đã từ chối'
    // );

    protected $listStatus = array(
        'pending' => 'model.clients.pending',
        'approved' => 'model.clients.approved',
        'processing' => 'model.clients.pending',
        'declined' => 'model.clients.rejected'
    );

    protected $listState = array(
        'processing' => 'model.social_security_claim.state_processing',
        'new' => 'model.procedure.status.new',
        'waiting' => 'model.payment_request.wait_for_pay',
        'paid' => 'payroll_status.paid'
    );

    protected $listType = array(
        'commuting fee' => 'model.request_payment.commuting_fee',
        'travel expense' => 'model.request_payment.travel_expense',
        'business fee' => 'model.payment_request.business_fee',
        'meeting expense' => 'model.request_payment.meeting_expense',
        'teaming expense' => 'model.request_payment.teaming_expense',
        'recruiting fee' => 'model.request_payment.recruiting_expense',
        'entertainment expense' => 'model.request_payment.entertainment_expense',
        'communication expense' => 'model.request_payment.communication_expense',
        'training expense' => 'model.request_payment.training_expense',
        'office rental fee' => 'model.request_payment.office_rental_fee',
        'consulting fee' => 'model.request_payment.consulting_fee',
        'insurance fee' => 'model.request_payment.insurance_fee',
        'bank charge' => 'model.request_payment.bank_charge',
        'stationery fee' => 'model.request_payment.stationery_fee',
        'advertisement fee' => 'model.request_payment.advertisement_fee',
        'other fee' => 'model.request_payment.other_expenses'
    );

    protected $listUnits = array(
        'vnd' => 'VND',
        'usd' => 'USD',
        'jpy' => 'JPY',
        'eur' => 'EUR',
        'khr' => 'KHR',
        'lak' => 'LAK',
        'thb' => 'THB',
        'sgd' => 'SGD',
        'myr' => 'MYR',
        'mmk' => 'MMK',
        'idr' => 'IDR',
        'php' => 'PHP',
        'bnd' => 'BND',
        'rmb' => 'RMB',
        'twd' => 'TWD',
        'hkd' => 'HKD',
        'krw' => 'KRW',
        'other' => 'model.client_applied_document.document_type_options.other'
    );

    public function __construct($params = []) {
        $this->data = $params['data'];
        $this->fromDate = isset($params['from_date']) ? date('d/m/Y', strtotime($params['from_date'])) : "##########";
        $this->toDate = isset($params['to_date']) ? date('d/m/Y', strtotime($params['to_date'])) : "##########";
        $this->timezone_name = $params['timezone_name'];
    }

    public function view(): View
    {
        foreach ($this->data as $key => $item) {
            if( $item ) {
                $approve = Approve::where('type', 'CLIENT_REQUEST_PAYMENT')->where('target_id', $item->id)->with('assignee')->first();
                $supplier = Supplier::where('id', $item->supplier_id)->with('beneficiary')->first();
                $amounts = PaymentRequestAmount::where('payment_request_id', $item->id)->get();
                $userName = '';
                if($approve && $approve->assignee && $approve->assignee->name && $approve->assignee->clientEmployee && $approve->assignee->clientEmployee->code){
                    $userName = '[' .$approve->assignee->clientEmployee->code.'] - '. $approve->assignee->name;
                }
                $this->data[$key]['nameUser'] = $userName;
                $this->data[$key]['supplier'] = $supplier;
                $this->data[$key]['amounts'] = $amounts;
            }
        }
        return view('exports.payment-request', [
            'data' => $this->data,
            'fromDate' =>$this->fromDate,
            'toDate' =>$this->toDate,
            'listStatus' => $this->listStatus,
            'listType' => $this->listType,
            'listUnits' => $this->listUnits,
            'timezone_name' => $this->timezone_name,
            'listState' => $this->listState
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            3 => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ]
        ];
    }
}
