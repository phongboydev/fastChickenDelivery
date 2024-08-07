<?php

namespace App\Support;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use Carbon\Carbon;

trait ApproveObserverTrait
{
    public function deleteApprove( $targetType, $targetID )
    {
        Approve::where('target_type', $targetType)->where('target_id', $targetID)->delete();
    }

    /**
     * @throws HumanErrorException
     */
    public function checkApproveBeforeDelete($target_id)
    {
        $approve = Approve::where([
            ['target_id', $target_id],
            ['is_final_step', 1]
        ])
            ->first();
        if ($approve) {
            throw new HumanErrorException(__("check_approve_before_delete"));
        }
    }
}
