<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Models\ClientEmployee;
use App\Models\PaidLeaveChange;
use App\Models\WorkScheduleGroup;
use App\Models\WorktimeRegister;
use App\Models\WorktimeRegisterCategory;
use App\Support\ApproveObserverTrait;
use Carbon\Carbon;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class WorktimeRegisterCategoryObserver
{

    public function deleted(WorktimeRegisterCategory $worktimeRegisterCategory)
    {
        $clientId = $worktimeRegisterCategory->client_id;

        logger('w1', [$worktimeRegisterCategory]);

        WorktimeRegister::where([
            'type' => $worktimeRegisterCategory->type,
            'sub_type' => $worktimeRegisterCategory->sub_type,
            'category' => $worktimeRegisterCategory->id
        ])->whereHas('client', function ($query) use ($clientId) {
            $query->where('clients.id', $clientId);
        })->update([
            'category' => 'other_leave'
        ]);
    }
}
