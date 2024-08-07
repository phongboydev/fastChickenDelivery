<?php

namespace App\Observers;

use App\User;
use App\Models\IglocalEmployee;
use App\Models\IglocalAssignment;
use App\Models\ApproveFlowUser;
use App\Models\Approve;

class IglocalEmployeeObserver
{
    public function updating(IglocalEmployee $iglocalEmployee)
    {
        if (!is_null($iglocalEmployee->user_id)) {
            User::where('id', $iglocalEmployee->user_id)->update([
                'name' => $iglocalEmployee->name,
                'code' => $iglocalEmployee->code
            ]);
        }
    }

    public function deleting(IglocalEmployee $iglocalEmployee)
    {
        // Disable user không cho login
        User::where('id', $iglocalEmployee->user_id)->update(['is_active' => 0]);

        // Xoa1 khỏi khách hàng đang phụ trách
        IglocalAssignment::where('iglocal_employee_id', $iglocalEmployee->id)->delete();

        // Tiến hành xóa approve còn đang duyệt và xóa khỏi approve flow
        $approveUserFlows = ApproveFlowUser::where('user_id', $iglocalEmployee->user_id)->get();

        if($approveUserFlows->isNotEmpty()){

            foreach($approveUserFlows as $approveUserFlow) {
                Approve::where('assignee_id', $approveUserFlow->user_id)
                        ->where('client_id', '000000000000000000000000')
                        ->whereNull('approved_at')->delete();
            }

            ApproveFlowUser::where('user_id', $iglocalEmployee->user_id)->delete();
        }
    }
}
