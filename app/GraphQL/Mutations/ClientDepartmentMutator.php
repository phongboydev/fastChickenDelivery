<?php

namespace App\GraphQL\Mutations;

use App\Exports\ClientDepartmentExport;
use App\Models\ClientDepartment;
use App\Models\ClientDepartmentEmployee;
use Exception;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Carbon;

class ClientDepartmentMutator
{

    public function deleteClientDepartment($root, array $args)
    {
        $id = $args['id'];
        try {

            ClientDepartment::where('parent_id', $id)->update([
                'parent_id' => null
            ]);

            ClientDepartmentEmployee::where('client_department_id', $id)->delete();

            ClientDepartment::where('id', $id)->delete();
            return 'ok';
        } catch (Exception $e) {
            logger()->error('deleteClientDepartment errror' . $e->getMessage());
            return 'fail';
        }
    }

    public function export($root, array $args)
    {   
        // Export excel
        $client_id = $args['client_id'];
        $extension = '.xlsx';
        $fileName = "ClientDepartmentExport_" . time() .  $extension;
        $pathFile = 'ClientDepartmentExport/' . $fileName;
        Excel::store((new ClientDepartmentExport($client_id)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }
}
