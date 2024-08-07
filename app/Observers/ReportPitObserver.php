<?php

namespace App\Observers;

use App\Models\ReportPit;
use App\Models\Approve;
use App\Models\ApproveGroup;

class ReportPitObserver
{
    public function deleted(ReportPit $reportPit)
    {
        $approve = Approve::where('type', 'INTERNAL_REQUEST_PIT_REPORT')->where('target_id', $reportPit->id)->first();

        if (!empty($approve)) {
            Approve::where('type', 'INTERNAL_REQUEST_PIT_REPORT')->where('target_id', $reportPit->id)->delete();
            ApproveGroup::where('id', $approve->approve_group_id)->delete();
        }
    }
}
