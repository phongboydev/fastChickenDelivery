<?php

namespace App\Events;

use App\Models\CalculationSheet;
use Illuminate\Foundation\Events\Dispatchable;

class DataImportCreatedEvent
{
    public $content;
    use Dispatchable;

    public function __construct($content)
    {
        $this->content = $content;
    }
}
