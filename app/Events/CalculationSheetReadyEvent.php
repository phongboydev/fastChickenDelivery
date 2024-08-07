<?php

namespace App\Events;

use App\Models\CalculationSheet;
use Illuminate\Foundation\Events\Dispatchable;

class CalculationSheetReadyEvent
{
    public CalculationSheet $calculationSheet;
    use Dispatchable;

    public function __construct(CalculationSheet $calculationSheet)
    {
        $this->calculationSheet = $calculationSheet;
    }
}
