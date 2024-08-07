<?php

namespace App\Events;

use App\Models\CalculationSheet;
use Illuminate\Foundation\Events\Dispatchable;

class CalculationSheetCalculatedEvent
{

    public CalculationSheet $calculationSheet;
    use Dispatchable;

    public function __construct(CalculationSheet $cs)
    {
        $this->calculationSheet = $cs;
    }
}
