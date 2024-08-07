<?php

namespace App\GraphQL\Mutations;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ClientProjectTimeLogExport;
use App\Support\MediaHelper;

class ClientProjectTimelogMutator
{
    public function export($root, array $args)
    : ?string
    {
        $client_project_id = $args['client_project_id'];
        $filter = $args['filter'];
        $filterEndDate = $args['filterEndDate'];
        $filterStartDate = $args['filterStartDate'];
        $filterClientEmployeeId = $args['filterClientEmployeeId'];
        // $client = ClientProjectTimelog::select('*')->where('client_project_id', $client_project_id)->get();

        $extension = 'xls';

        $fileName =  'ClientProjectTimeLog' . '.' . $extension;

        $pathFile = 'ClientEmployeeExport/' . $fileName;

        Excel::store((new ClientProjectTimeLogExport(
            $client_project_id,
            $filter,
            $filterStartDate,
            $filterEndDate,
            $filterClientEmployeeId
        )), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'file' => MediaHelper::getPublicTemporaryUrl($pathFile)
        ];

        return json_encode($response);
    }
}
