<?php

namespace App\Events;

use App\Models\CalculationSheet;
use Illuminate\Foundation\Events\Dispatchable;

class CalculationSheetCreatedEvent
{

    public CalculationSheet $sheet;
    use Dispatchable;

    public function __construct(CalculationSheet $sheet)
    {
        $this->sheet = $sheet;
    }
}
