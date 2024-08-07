<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
// use Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use App\Exports\DebitRequestExport;
use App\Imports\DebitPaymentImport;
use Carbon\Carbon;
use App\Models\DebitRequest;
use App\Models\Client;
use App\Models\CalculationSheet;
use App\Support\MediaHelper;

class DebitRequestMutator
{
    public function exportExcel($root, array $args)
    {
        $debitRequestId = $args['id'];
        $extension = 'xlsx';
        $fileName = 'DEBIT_REQUEST' . '.' . $extension;
        $pathFile = 'DebitRequestExport/' . $fileName;
        Excel::store((new DebitRequestExport($debitRequestId)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];
        return json_encode($response);
    }

    public function exportExcelDemo($root, array $args)
    {
        $extension = 'xlsx';
        $fileName = 'DEBIT_REQUEST' . '.' . $extension;
        $pathFile = 'DebitRequestExport/' . $fileName;
        Excel::store((new DebitRequestExport()), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];
        return json_encode($response);
    }

    public function log($root, array $args)
    {
        $clientId = $args['clientId'];
        $logType = $args['type'];
        $logContent = $args['content'];
        $client = Client::find($clientId);
        if ($client) {
            $client->addLog($logType, $logContent);
            return $logContent;
        }

        return "Client was not found";
    }

    public function exportDebitPayment($root, array $args)
    {
        $bank = $args['bank'];
        $payrollId = $args['payroll_id'];

        $payroll = CalculationSheet::where('id', $payrollId)->with('client')->first();
        $clientCode = $payroll->client['code'];

        $uniqueId = time();

        $fileName = $payroll->client_id . '/' . "{$clientCode}_debit_payment_{$bank}_{$uniqueId}.xlsx";

        $pathFile = 'DebitPayment/' . $fileName;

        Excel::import(new DebitPaymentImport(
            $bank,
            $payroll,
            $pathFile
        ), storage_path('app/DebitPayment/' . $bank . '.xlsx'));

        $response = [
            'error' => false,
            'name' => $fileName,
            'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
        ];

        return json_encode($response);
    }
}
