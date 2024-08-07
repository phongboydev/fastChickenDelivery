<?php

namespace App\Pdfs;

use App\Models\Contract;
use InvalidArgumentException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Storage;

class ContractDocToPdf extends AbstractOfficeToPdfGenerator
{

    protected Contract $model;

    public function __construct(Contract $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    protected function getFileName(): string
    {
        return 'contract'.$this->model->id.'.pdf';
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getOfficeFileLocalPath(): string
    {
        $media = $this->model->getContractMedia();
        if (!$media) {
            throw new InvalidArgumentException("Doc is not ready to be converted.");
        }

        $temporaryDirectory = (new TemporaryDirectory())->create();
        // TODO is it okay to force it to be .docx
        $tempFileName = $this->getFileName() . ".docx";
        $tempPath = $temporaryDirectory->path($tempFileName);

        file_put_contents(
           $tempPath,
           Storage::cloud()->get($media->getPath())
       );
        return $tempPath;
    }
}
