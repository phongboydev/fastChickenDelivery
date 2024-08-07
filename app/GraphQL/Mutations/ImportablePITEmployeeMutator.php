<?php

namespace App\GraphQL\Mutations;

use App\Exports\DefaultPITDataExport;
use App\Imports\PITDataImport;
use App\Jobs\DeleteFileJob;
use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportablePITEmployeeMutator {
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function import($root, array $args)
    {
        try {
            $PITDataImport = new PITDataImport($args['file'], $args['client_id']);

            Excel::import($PITDataImport, $args['file']);

        } catch (\Exception $e) {

            throw $e;
        }

    }

    /**
     * Export example file to user input data
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function exportDefaultFile($root, array $args)
    {
        $extension = '.xlsx';
        $fileName = "Expat_import_template__" . uniqid() .  $extension;
        $pathFile = 'PITDataImport/' . $fileName;
        $client = Client::find($args['client_id']);
        Excel::store((new DefaultPITDataExport($client->company_name, $args['from'], $args['to'])), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        // Delete file
        DeleteFileJob::dispatch($pathFile)->delay(now()->addMinutes(3));

        return json_encode($response);
    }
}
