<?php

namespace App\Observers;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use App\Models\ApproveFlowUser;
use App\Models\ClientWorkflowSetting;
use App\Support\Constant;
use App\Support\ErrorCode;
use App\User;

class ApproveFlowUserObserver
{

    /**
     * Handle the ApproveFlowUser "created" event.
     *
     * @return void
     */
    public function created(ApproveFlowUser $approveFlowUser)
    {
    }

    /**
     * Handle the ApproveFlowUser "deleting" event.
     *
     * @return bool
     */
    public function deleting(ApproveFlowUser $approveFlowUser)
    {
        $approveFlow = $approveFlowUser->approveFlow;

        if ($approveFlow) {
            $flow_name = preg_replace("/[^a-zA-Z]+/", "", $approveFlow->flow_name);

            if (ctype_upper($flow_name)) {
                // Xóa tất cả approve chưa được duyệt assign đến user này
                $hasInProgressApprove = Approve::where('client_id', $approveFlow->client_id)
                    ->where('type', $approveFlow->flow_name)
                    ->where('step', $approveFlow->step)
                    ->where('client_employee_group_id', $approveFlow->group_id)
                    ->where('assignee_id', $approveFlowUser->user_id)
                    ->whereNull('approved_at')
                    ->whereNull('declined_at')
                    ->exists();

                if ($hasInProgressApprove) {
                    throw new HumanErrorException(__('error.has_request_still_pending_approve'), ErrorCode::ERR0002);
                }
            }
        }
    }

    /**
     * Handle the ApproveFlowUser "deleted" event.
     *
     */
    public function deleted(ApproveFlowUser $approveFlowUser)
    {
        $approveFlowUser->load('approveFlow');
        $user = User::select('*')->where('id', $approveFlowUser->user_id)->first();
        if (!empty($user)) {
            $user->refreshPermissions();
        }

        $approveFlow = $approveFlowUser->approveFlow;


        logger('An approve is removed when delete approve flow user: ' . $approveFlowUser->id);
    }
}
