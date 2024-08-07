<?php

namespace App\Pdfs;

use App\Exceptions\PdfApiFailedException;
use App\Pdfs\AbstractPdfGenerator;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\HasMedia;
use Storage;

abstract class AbstractOfficeToPdfGenerator extends AbstractPdfGenerator
{

    /**
     * @throws \App\Exceptions\PdfApiFailedException
     */
    public function generate()
    {
        $localFilePath = $this->getOfficeFileLocalPath();
        $this->officeToPdf($localFilePath);
    }

    abstract public function getOfficeFileLocalPath() : string;

    /**
     * @throws \App\Exceptions\PdfApiFailedException
     */
    protected function officeToPdf(string $localFilePath)
    {
        if (!file_exists($localFilePath)) {
            throw new PdfApiFailedException("Office file local path was not existed: " . $localFilePath);
        }

        if (!config('vpo.gotenberg.enabled', false)) {
            // do nothing
            return;
        }

        $path = $this->getFileName();
        $pdfPath = Storage::disk('local')->path($path);
        $res = fopen($localFilePath, "r");

        $response = Http::attach("files", $res, "index.html")
                        ->sink($pdfPath)
                        ->post(
                            config('vpo.gotenberg.url').  '/forms/libreoffice/convert',
                            []
                        );

        if (!$response->successful()) {
            throw new PdfApiFailedException($response->body());
        }

        $this->storeToMedia($pdfPath, $this->hasMediaModel);
    }

}
