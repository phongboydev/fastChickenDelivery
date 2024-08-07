<?php

namespace App\Pdfs;

use App\Pdfs\PdfGeneratorInterface;
use Spatie\MediaLibrary\HasMedia;

abstract class AbstractPdfGenerator implements PdfGeneratorInterface
{

    protected HasMedia $hasMediaModel;
    protected string $pdfMediaCollection = 'pdf';

    /**
     * @param  \Spatie\MediaLibrary\HasMedia  $model
     */
    public function __construct(HasMedia $model) { $this->hasMediaModel = $model; }

    /**
     *
     */
    protected function getFileName(): string
    {
        // Should be override by subclass to avoid name conflict when running in parrallel
        return 'tmp_pdf_generate_output'.'_'.uniqid().'.pdf';
    }

    protected function storeToMedia(string $pathToPdfFile, HasMedia $model)
    {
        // PDF should be single collection
        $model->addMedia($pathToPdfFile)
              ->toMediaCollectionOnCloudDisk($this->pdfMediaCollection);
    }

    abstract public function generate();
}
