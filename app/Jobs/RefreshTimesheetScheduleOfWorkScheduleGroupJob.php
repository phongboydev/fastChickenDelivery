<?php

namespace App\Jobs;

use App\Models\ClientEmployee;
use App\Models\WorkScheduleGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshTimesheetScheduleOfWorkScheduleGroupJob implements ShouldQueue, ShouldBeUnique
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public int $uniqueFor = 3600;

    protected WorkScheduleGroup $workScheduleGroup;

    /**
     * @param  \App\Models\WorkScheduleGroup  $workScheduleGroup
     */
    public function __construct(WorkScheduleGroup $workScheduleGroup)
    {
        $this->workScheduleGroup = $workScheduleGroup;
    }

    public function handle()
    {
        $templateId = $this->workScheduleGroup->work_schedule_group_template_id;
        ClientEmployee::where('work_schedule_group_template_id', $templateId)
                      ->chunk(100, function ($clientEmployees) {
                          /** @var ClientEmployee $clientEmployee */
                          foreach ($clientEmployees as $clientEmployee) {
                              $clientEmployee->refreshTimesheetByWorkScheduleGroupAsync($this->workScheduleGroup);
                          }
                      });
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->workScheduleGroup->id;
    }
}
