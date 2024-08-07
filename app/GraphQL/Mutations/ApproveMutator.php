<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use App\Support\Constant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ApproveMutator
{
    public function getApproves($root, array $args)
    {
        return Approve::has('originalCreator');
    }

    public function deleteApprovesByGroupId($root, array $args)
    {
        $status = Constant::PENDING_STATUS;
        $approve = Approve::where('approve_group_id', $args['approve_group_id'])->latest()->first();

        $user = Auth::user();

        if (!$approve) {
            throw new HumanErrorException(__("application_is_not_pending_status"));
        }

        if (!$user && $approve->client_id != $user->client_id) {
            throw new HumanErrorException(__("authorized"));
        }

        if (!is_null($approve->approved_at) && $approve->is_final_step == 1) {
            $status = Constant::APPROVE_STATUS;
        } elseif (!is_null($approve->declined_at)) {
            $status = Constant::DECLINED_STATUS;
        }

        if($status == Constant::PENDING_STATUS) {
            return Approve::where('approve_group_id', $args['approve_group_id'])->delete();
        } else {
            throw new HumanErrorException(__("check_approve_before_delete"));
        }

        return true;
    }
}
