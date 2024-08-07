<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Models\PaymentRequestExportTemplate;
use Illuminate\Support\Carbon;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PaymentRequestExport;
use App\Models\Client;
use App\Models\ClientEmployee;
use App\Models\Supplier;
use App\Models\Approve;
use Illuminate\Support\Facades\Storage;
use App\Support\Constant;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentRequestAmount;
use App\Models\PaymentRequestStateHistory;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use App\Exceptions\PdfApiFailedException;

class PaymentRequestMutator
{

    public function paymentRequests($root, array $args)
    {
        return PaymentRequest::has('clientEmployee');
    }

    public function create($root, array $args)
    {
        DB::beginTransaction();

        try {
            // Validate args
            $valid = '';
            foreach ($args['amount'] as $key => $value) {
                $valid = $value;
            }

            if (isset($args['amount']) && $valid['amount']) {
                $payment = PaymentRequest::create(array(
                    'client_id' =>  $args['client_id'],
                    'client_employee_id' =>  $args['client_employee_id'],
                    'supplier_id' => $args['supplier_id'],
                    'business_trip_id' => $args['business_trip_id'],
                    'title' => $args['title'],
                    'total_amount' => $args['total_amount'],
                    'state' => $args['state'],
                    'type' => $args['type'],
                    'category' => $args['category'],
                    'status' =>  $args['status'],
                ));

                // Insert amount
                foreach ($args['amount'] as $item) {
                    PaymentRequestAmount::create(
                        [
                            'amount' => $item['amount'],
                            'note' => $item['note'],
                            'unit' => $item['unit'],
                            'payment_request_id' => $payment->id
                        ]
                    );
                }

                DB::commit();

                return $payment;
            } else {
                throw new CustomException(
                    'Please double check the information on the required fields.',
                    'ValidationException'
                );
            }
        } catch (\Throwable $e) {

            DB::rollback();

            echo $e->getMessage();
            // something went wrong
            return 'not ok';
        }
    }

    public function exportExcel($root, array $args)
    {

        $fromDate = isset($args['from_date']) ? $args['from_date'] : null;
        $toDate = isset($args['to_date']) ? $args['to_date'] : null;

        $query = PaymentRequest::where('client_id', $args['client_id'])->authUserAccessible();

        if ($fromDate && $toDate) {
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        $data = $query->orderBy('created_at')->get();

        $user = auth()->user();
        $timezone_name = !empty($user->timezone_name) ? $user->timezone_name : Constant::TIMESHEET_TIMEZONE;

        $params = array(
            'data' => $data,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'timezone_name' => $timezone_name
        );

        // Export excel
        $extension = '.xlsx';
        $fileName = "PaymentRequest_" . time() .  $extension;
        $pathFile = 'PaymentRequestExport/' . $fileName;
        Excel::store((new PaymentRequestExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function exportPDF($root, array $args)
    {
        $dompdf = new Dompdf();

        $query = PaymentRequest::where('client_id', $args['client_id'])->where('id', $args['id'])->authUserAccessible();

        $item = $query->first();

        $user = auth()->user();
        $timezone_name = !empty($user->timezone_name) ? $user->timezone_name : Constant::TIMESHEET_TIMEZONE;

        $listStatus = array(
            'pending' => 'model.clients.pending',
            'approved' => 'model.clients.approved',
            'processing' => 'model.clients.pending',
            'declined' => 'model.clients.rejected'
        );

        $listState = array(
            'processing' => 'model.social_security_claim.state_processing',
            'new' => 'model.procedure.status.new',
            'waiting' => 'model.payment_request.wait_for_pay',
            'paid' => 'payroll_status.paid'
        );

        $listType = array(
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

        $listUnits = array(
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

        if( $item ) {
            $approve = Approve::where('type', 'CLIENT_REQUEST_PAYMENT')->where('target_id', $item->id)->with('assignee')->orderBy('step', 'asc')->get();
            $supplier = Supplier::where('id', $item->supplier_id)->with('beneficiary')->first();
            $amounts = PaymentRequestAmount::where('payment_request_id', $item->id)->get();
            $history = PaymentRequestStateHistory::where('payment_request_id', $item->id)->with('clientEmployee')->get();
            $item['approve'] = $approve;
            $item['supplier'] = $supplier;
            $item['amounts'] = $amounts;
            $item['history'] = $history;

            $pdfHTML = mb_convert_encoding(View::make('pdfs.payment-request', [
                'item' => $item,
                'listStatus' => $listStatus,
                'listType' => $listType,
                'listUnits' => $listUnits,
                'timezone_name' => $timezone_name,
                'listState' => $listState
            ]), 'HTML-ENTITIES', 'UTF-8');

            $dompdf->loadHtml($pdfHTML);
            $dompdf->set_paper($args['size_paper'], $args['orientation']);
            $dompdf->render();
            $storagePath  = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();

            $fileName = $item["title"] . '_' . $item["full_name"] . "pdf";
            $sanitizedFileName = $this->sanitizeFileName($fileName);
            $filePath = $storagePath . $sanitizedFileName;

            $fileHandle = fopen($filePath, 'w');

            fwrite($fileHandle, $dompdf->output());

            $response =  array(
                'name' => $fileName,
                'file' => "data:application/pdf;base64,".base64_encode(file_get_contents($filePath))
            );

            unlink($filePath);

            return json_encode($response);
        }
    }

    public function getLatestTemplatePaymentRequest($root, array $args)
    {
        $auth = Auth::user();
        return PaymentRequestExportTemplate::where('client_id', $auth->client_id)->orderBy('created_at', 'desc')->first();
    }

    private function sanitizeFileName($fileName) {
        // Remove special characters
        $fileName = preg_replace('/[^\w\-\. ]+/', '', $fileName);
        return $fileName;
    }
}
