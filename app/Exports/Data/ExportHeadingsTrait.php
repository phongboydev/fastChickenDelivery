<?php

namespace App\Exports\Data;

trait ExportHeadingsTrait
{
    public function headings(): array
    {
        $first = $this->query()->first();
        $headings = [];
        if ($first) {
            $headings = array_keys($first->toArray());
        }
        return $headings;
    }
}
