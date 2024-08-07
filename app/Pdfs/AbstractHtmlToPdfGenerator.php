<?php

namespace App\Pdfs;

use App\Exceptions\PdfApiFailedException;
use App\Pdfs\AbstractPdfGenerator;
use Illuminate\Support\Facades\Http;
use Storage;

abstract class AbstractHtmlToPdfGenerator extends AbstractPdfGenerator
{

    /**
     * @throws \App\Exceptions\PdfApiFailedException
     */
    public function generate()
    {
        $html = $this->generateHtml();
        $this->htmlToPdf($html);
    }

    abstract public function generateHtml(): string;

    /**
     * @throws \App\Exceptions\PdfApiFailedException
     */
    protected function htmlToPdf($html)
    {
        if (!config('vpo.gotenberg.enabled', false)) {
            // do nothing
            return;
        }

        $path = $this->getFileName();
        $pdfPath = Storage::disk('local')->path($path);

        $response = Http::attach("files", $html, "index.html")
                        ->sink($pdfPath)
                        ->post(
                            config('vpo.gotenberg.url').  '/forms/chromium/convert/html',
                            []
                        );

        if (!$response->successful()) {
            throw new PdfApiFailedException($response->body());
        }

        $this->storeToMedia($pdfPath, $this->hasMediaModel);
        // echo $pdfPath;
        // clean up temporary dir
        // $temporaryDir->delete();
    }

}
