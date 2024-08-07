<?php

namespace App\GraphQL\Mutations;

use App\Exports\SupplierExport;
use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;


class SupplierMutator
{
public function export($root, array $args)
    {

        $data = Supplier::where('client_id', $args['client_id'])->with('beneficiary')->orderBy('created_at', 'desc')->get();
        // Export excel
        $extension = '.xlsx';
        $fileName = "SupplierExport_" . time() .  $extension;
        $pathFile = 'SupplierExport/' . $fileName;


        Excel::store((new SupplierExport($data)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }
}
