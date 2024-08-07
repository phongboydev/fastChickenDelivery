<?php

namespace App\Jobs;

use App\Pdfs\PdfGeneratorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePdfJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected PdfGeneratorInterface $generator;

    /**
     * Accept any PdfGeneratorInterface and process generating on model
     * @param  \App\Pdfs\PdfGeneratorInterface  $generator
     */
    public function __construct(PdfGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->generator->generate();
    }
}
