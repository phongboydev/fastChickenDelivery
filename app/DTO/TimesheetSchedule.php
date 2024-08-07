<?php

namespace App\DTO;

use Spatie\DataTransferObject\DataTransferObject;

class TimesheetSchedule extends DataTransferObject
{

    public string $start;
    public int $start_next_day = 0;
    public string $end;
    public int $end_next_day = 0;
    public string $date;
    public string $state;
    public bool $disabled;
    public float $duration;
    public float $effective_duration = 0; // duration that counts towards the work hours
    public string $state_label;

}
