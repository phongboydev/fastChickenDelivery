<?php

namespace App\Pdfs;

interface PdfGeneratorInterface
{

    /**
     * @throws \App\Exceptions\PdfApiFailedException
     */
    public function generate();
}
