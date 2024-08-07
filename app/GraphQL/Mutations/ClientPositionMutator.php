<?php

namespace App\GraphQL\Mutations;

use App\Exports\ClientPositionExport;
use Exception;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;
use App\Models\ClientPosition;
class ClientPositionMutator
{

    public function export($root, array $args)
    {
        $client_id = $args['client_id'];
        $data = ClientPosition::select('name', 'code')->where('client_id', $client_id)->orderBy('created_at', 'desc')->get();
        // Export excel
        $extension = '.xlsx';
        $fileName = "ClientPositionExport_" . time() .  $extension;
        $pathFile = 'ClientPositionExport/' . $fileName;


        Excel::store((new ClientPositionExport($data)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }
}
