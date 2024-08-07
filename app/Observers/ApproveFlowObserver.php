<?php

namespace App\Observers;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ClientWorkflowSetting;
use App\Support\Constant;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class ApproveFlowObserver
{

    public function creating(ApproveFlow $approveFlow)
    {
        if ($approveFlow->level == 1) {
            if (auth()->user()->getSettingAdvancedPermissionFlow($approveFlow->client_id)) {
                $permission_list = collect(Constant::ADVANCED_PERMISSION_FLOW);
                foreach ($permission_list as $cate) {
                    foreach ($cate['sub'] as $sub) {
                        if ($sub['name'] === $approveFlow->flow_name) {
                            if (!$cate['has_group'] && $approveFlow->group_id != 0) {
                                throw new AuthenticationException(__('error.permission'));
                            }
                        }
                    }
                }
            }
        }
    }

    public function created(ApproveFlow $approveFlow)
    {
        if ($approveFlow->level == 1) {
            if (auth()->user()->getSettingAdvancedPermissionFlow($approveFlow->client_id)) {
                // Create CRUDIE to ApproveFlow
                $permission_list = collect(Constant::ADVANCED_PERMISSION_FLOW);

                foreach ($permission_list as $cate) {
                    foreach ($cate['sub'] as $sub) {
                        if ($sub['name'] === $approveFlow->flow_name) {
                            foreach ($sub['permission'] as $permission) {
                                $item = $approveFlow->flow_name . "-" . $permission;
                                ApproveFlow::updateOrCreate([
                                    'flow_name' => $item,
                                    'client_id' => $approveFlow->client_id,
                                    'level' => 2,
                                    'step' => 1,
                                    "group_id" => isset($approveFlow->group_id) ? $approveFlow->group_id : 0
                                ]);
                            }
                            break;
                        }
                    }
                }
            }
        }
    }

    public function deleted(ApproveFlow $approveFlow)
    {
        logger('ApproveFlowObserver::deleted - ' . $approveFlow->id);

        // Xóa tất cả approve chưa được duyệt của approve flow
        // Approve::where('client_id', $approveFlow->client_id)
        //         ->where('type', $approveFlow->flow_name)
        //         ->where('step', $approveFlow->step)
        //         ->whereNull('approved_at')
        //         ->whereNull('declined_at')
        //         ->delete();

        // Approve::where('client_id', $approveFlow->client_id)
        // ->where('type', $approveFlow->flow_name)
        // ->where('step', ($approveFlow->step - 1))->update(['approved_at' => null, 'declined_at' => null]);
    }
}
