<?php

namespace App\Exceptions;

use Exception;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;
use Illuminate\Support\Facades\Storage;
use App\Exports\ImportErrorExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Support\MediaHelper;

class DownloadFileErrorException extends CustomException
{
    /**
     * CustomException constructor.
     *
     * @param  string  $message
     * @param  string  $reason
     * @return void
     */
    public function __construct($errors, $importFile)
    {

        $reason = '';

        $errorFile = 'temp/import_errors_' .  time() . '.xlsx';

        Excel::store((new ImportErrorExport(
            $errors,
            $importFile
        )), $errorFile, 'minio');

        $downloadPath = MediaHelper::getPublicTemporaryUrl($errorFile);

        $errorMessage = json_encode(['download' => $downloadPath, 'errors' => $errors]);
        
        Storage::disk('local')->delete($importFile);

        parent::__construct($errorMessage, $reason);
    }

}
