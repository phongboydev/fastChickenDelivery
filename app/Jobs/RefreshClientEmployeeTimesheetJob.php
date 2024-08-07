<?php

namespace App\Jobs;

use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RefreshClientEmployeeTimesheetJob implements ShouldQueue, ShouldBeUnique
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ClientEmployee $ce;
    protected WorkScheduleGroup $workScheduleGroup;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public int $uniqueFor = 600;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    public function __construct(ClientEmployee $ce, WorkScheduleGroup $workScheduleGroup)
    {
        $this->ce = $ce;
        $this->workScheduleGroup = $workScheduleGroup;
    }

    public function handle()
    {
        $this->ce->refreshTimesheetByWorkScheduleGroup($this->workScheduleGroup);
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->ce->id . "|" . $this->workScheduleGroup->id;
    }
}
