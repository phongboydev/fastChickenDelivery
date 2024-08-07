<?php

namespace App\GraphQL\Mutations;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\ClientWorkflowSetting;
use App\Support\Constant;
use App\User;
use App\Exceptions\HumanErrorException;
use Illuminate\Support\Facades\DB;
use App\Support\ErrorCode;

class ApproveFlowUserMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function assignApproveFlowUser($root, array $args)
    {

        $userId = $args['user_id'];
        $flow = ApproveFlow::query()->authUserAccessible()
            ->findOrFail($args['approve_flow_id']);

        /** @var Collection $users */
        $users = $flow->approveFlowUsers->keyBy("user_id");
        if ($users->has($userId)) {
            return false;
        }


        $user = User::where('id', $userId)->first();

        // Check that the permission mode is enabled
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $flow->client_id)->first();
        $data = [];
        // Get Group ID
        $group_Id = $flow->group_id;

        if (!empty($clientWorkflowSetting->advanced_permission_flow)) {

            // Add user to sub-flow
            $permission_list = collect(Constant::ADVANCED_PERMISSION_FLOW);
            $parentPermission =  $permission_list->contains('name', $flow->flow_name);

            if ($parentPermission) {
                $items =  $permission_list->firstWhere('name', $flow->flow_name);
                foreach ($items['sub'] as $item) {
                    $current_approval_flow = ApproveFlow::select('id')->where(['flow_name' => $item['name'], 'group_id' => $group_Id, 'client_id' => $flow->client_id])->first();
                    ApproveFlowUser::create([
                        'user_id' => $userId,
                        'approve_flow_id' => $current_approval_flow->id,
                        'group_id' => $group_Id
                    ]);

                    if ($user) {
                        $read_access = $item['name'] . "-read";
                        $AF_current = ApproveFlow::select('id')->where(['flow_name' => $read_access, 'group_id' => $group_Id, 'client_id' => $flow->client_id])->first();
                        ApproveFlowUser::create([
                            'user_id' => $userId,
                            'approve_flow_id' => $AF_current->id,
                            'group_id' => $group_Id
                        ]);
                        $user->forceAdvanceGivePermissionTo(false, $read_access, $group_Id);
                    }
                }

                $approveFlowUser = new ApproveFlowUser();
                $data['user_id'] = $userId;
                $data['approve_flow_id'] = $args['approve_flow_id'];
                $data['group_id'] =  $group_Id;
                $approveFlowUser->fill($data);
            } else {
                //  Add read access control based on permission
                if ($user) {
                    foreach ($permission_list as $cate) {
                        foreach ($cate['sub'] as $sub) {
                            if ($sub['name'] === $flow->flow_name) {
                                /*  Add read access control based on permission */
                                $read_access = $flow->flow_name . "-read";
                                $AF_child = ApproveFlow::select('id')->where(['flow_name' => $read_access, 'group_id' => $group_Id, 'client_id' => $flow->client_id])->first();
                                // Child
                                ApproveFlowUser::create([
                                    'user_id' => $userId,
                                    'approve_flow_id' => $AF_child->id,
                                    'group_id' => $group_Id
                                ]);
                                $user->forceAdvanceGivePermissionTo(false, $flow->flow_name, $group_Id);
                                $user->forceAdvanceGivePermissionTo(false, $read_access, $group_Id);
                                break;
                            }
                        }
                    }
                }
            }

            if (isset($args['parent_id'])) {
                $data['parent_id'] = $args['parent_id'];
            }


        }

        // check if approve flow and user is belong to same client
        if (User::query()->where("is_internal", 0)
            ->where("client_id", $flow->client_id)
            ->exists()
        ) {
            $approveFlowUser = new ApproveFlowUser();
            $data['user_id'] = $userId;
            $data['approve_flow_id'] = $args['approve_flow_id'];
            $data['group_id'] =  $group_Id;
            $approveFlowUser->fill($data);
            $approveFlowUser->save();

            $user->refreshPermissions();

            return true;
        } elseif (User::query()->where("is_internal", 1)) {
            $approveFlowUser = new ApproveFlowUser();
            $data['user_id'] = $userId;
            $data['approve_flow_id'] = $args['approve_flow_id'];
            $data['group_id'] =  $group_Id;
            $approveFlowUser->fill($data);

            $approveFlowUser->save();

            $user->refreshPermissions();

            return true;
        }
        throw new \InvalidArgumentException("Assigned user_id and ApproveFlow are not belong to same client");
    }

    public function assignMultipleApproveFlowUser($root, array $args)
    {

        $userIds = $args['user_ids'];
        $flow = ApproveFlow::query()->authUserAccessible()
            ->findOrFail($args['approve_flow_id']);

        /** @var Collection $users */
        $users = $flow->approveFlowUsers->pluck("user_id");
        foreach ($userIds as $userId) {
            if ($users->contains($userId)) {
                return false;
            }
        }

        // Check that the permission mode is enabled
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $flow->client_id)->first();
        $parentPermission = null;
        $items = null;
        $groupId = $flow->group_id;
        // Add user to sub-flow
        $permissionList = collect(Constant::ADVANCED_PERMISSION_FLOW);
        $isApproveFlowAdvancedPermission = in_array($flow->flow_name, $permissionList->pluck('name')->toArray());
        $isAdvancedPermissionFlow = $clientWorkflowSetting->advanced_permission_flow;
        $approvalFlowByNames = collect();
        if ($isAdvancedPermissionFlow) {
            $parentPermission = $permissionList->contains('name', $flow->flow_name);
            $flowNames = [];
            $approvalFlowByNames = ApproveFlow::select('id', 'flow_name')
                ->where([
                    'group_id' => $groupId,
                    'client_id' => $flow->client_id
                ]);
            if ($parentPermission) {
                $items = $permissionList->firstWhere('name', $flow->flow_name);
                foreach ($items['sub'] as $item) {
                    $flowNames[] = $item['name'];
                    $flowNames[] = $item['name'] . "-read";
                }

                $approvalFlowByNames->whereIn('flow_name', $flowNames);
            } else {
                $approvalFlowByNames->whereIn('flow_name', [$flow->flow_name, $flow->flow_name . "-read"]);
            }
            $approvalFlowByNames = $approvalFlowByNames->get()->keyBy('flow_name');
        }

        DB::beginTransaction();
        try {
            $users = User::whereIn('id', $userIds)->get();
            $users->each(function ($user) use ($isAdvancedPermissionFlow, $parentPermission, $permissionList, $flow, $groupId, $args, $items, $approvalFlowByNames, $isApproveFlowAdvancedPermission) {
                if (!empty($isAdvancedPermissionFlow)) {
                    if ($parentPermission) {
                        foreach ($items['sub'] as $item) {
                            $currentApprovalFlow = $approvalFlowByNames->get($item['name']);
                            ApproveFlowUser::create([
                                'user_id' => $user->id,
                                'approve_flow_id' => $currentApprovalFlow->id,
                                'group_id' => $groupId
                            ]);

                            $readAccess = $currentApprovalFlow->flow_name . "-read";
                            $currentApprovalFlowRead = $approvalFlowByNames->get($readAccess);
                            ApproveFlowUser::create([
                                'user_id' => $user->id,
                                'approve_flow_id' => $currentApprovalFlowRead->id,
                                'group_id' => $groupId
                            ]);
                            $user->forceAdvanceGivePermissionTo(false, $readAccess, $groupId);
                        }

                        $approveFlowUser = new ApproveFlowUser();
                        $data['user_id'] = $user->id;
                        $data['approve_flow_id'] = $args['approve_flow_id'];
                        $data['group_id'] = $groupId;
                        $approveFlowUser->fill($data);
                    } else {
                        foreach ($permissionList as $cate) {
                            foreach ($cate['sub'] as $sub) {
                                if ($sub['name'] === $flow->flow_name) {
                                    /*  Add read access control based on permission */
                                    $readAccess = $flow->flow_name . "-read";
                                    $currentApprovalFlowRead = $approvalFlowByNames->get($readAccess);
                                    // Child
                                    ApproveFlowUser::create([
                                        'user_id' => $user->id,
                                        'approve_flow_id' => $currentApprovalFlowRead->id,
                                        'group_id' => $groupId
                                    ]);
                                    $user->forceAdvanceGivePermissionTo(false, $flow->flow_name, $groupId);
                                    $user->forceAdvanceGivePermissionTo(false, $readAccess, $groupId);
                                    break;
                                }
                            }
                        }
                    }

                    if (isset($args['parent_id'])) {
                        $data['parent_id'] = $args['parent_id'];
                    }
                }
                // check if approve flow and user is belong to same client
                if (User::query()->where("is_internal", 0)
                    ->where("client_id", $flow->client_id)
                    ->exists()
                ) {
                    $approveFlowUser = new ApproveFlowUser();
                    $data['user_id'] = $user->id;
                    $data['approve_flow_id'] = $args['approve_flow_id'];
                    $data['group_id'] = $groupId;
                    $approveFlowUser->fill($data);

                    $approveFlowUser->save();


                } elseif (User::query()->where("is_internal", 1)) {
                    $approveFlowUser = new ApproveFlowUser();
                    $data['user_id'] = $user->id;
                    $data['approve_flow_id'] = $args['approve_flow_id'];
                    $data['group_id'] = $groupId;
                    $approveFlowUser->fill($data);

                    $approveFlowUser->save();
                }

                // Refresh permission by condition
                if ($isAdvancedPermissionFlow && !$isApproveFlowAdvancedPermission) {
                    $user->refreshPermissions();
                }
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \InvalidArgumentException("Assigned user_id and ApproveFlow are not belong to same client");
        }

        return true;
    }

    public function reassignApproveFlowUser($root, array $args)
    {
        $oldUserId = $args['old_user_id'];
        $newUserId = $args['new_user_id'];
        $assign_action = $args['assign_action'];

        $flow = ApproveFlow::query()->authUserAccessible()
            ->with([
                'approveFlowUsers' => function ($q) use ($oldUserId) {
                    $q->where('approve_flow_users.parent_id', $oldUserId);
                }
            ])
            ->withCount([
                'approveFlowUsers as has_child' => function ($q) use ($oldUserId) {
                    $q->where('approve_flow_users.parent_id', $oldUserId);
                }
            ])
            ->findOrFail($args['approve_flow_id']);

        // Check that the permission mode is enabled
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $flow->client_id)->first();

        // Check if the user is authorized in this
        if (!$clientWorkflowSetting->advanced_permission_flow) {
            return false;
        } else {
            $oldUser = User::where('id', $oldUserId)->first();
            $newUser = User::where('id', $newUserId)->first();
            $permission_list = collect(Constant::ADVANCED_PERMISSION_FLOW);
            $parentPermission =  $permission_list->contains('name', $flow->flow_name);

            // child only
            if (!$parentPermission) {
                // Get Group ID
                $group_Id = $flow->group_id;

                foreach ($permission_list as $cate) {
                    foreach ($cate['sub'] as $sub) {
                        if ($sub['name'] === $flow->flow_name) {
                            foreach ($sub['permission'] as $permission) {
                                $crudie = $flow->flow_name . "-" . $permission;

                                // ApproveFlowUser - crudie
                                $current_approval_flow_crudie = ApproveFlow::where(['flow_name' => $crudie, 'client_id' => $flow->client_id, 'group_id' => $group_Id])->withCount([
                                    'approveFlowUsers as has_permission' => function ($q) use ($oldUserId) {
                                        $q->where('approve_flow_users.user_id', $oldUserId);
                                    }
                                ])->first();

                                // oldUser has permission
                                if ($current_approval_flow_crudie->has_permission) {

                                    // $old_parent
                                    $new_parent = ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'group_id' => $group_Id, 'user_id' => $newUserId])->first();

                                    // Revoke permission - Thu hồi quyền
                                    ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'group_id' => $group_Id, 'user_id' => $oldUserId])->delete();

                                    // Không ghi nhận quyền đọc vào parent mới - vì user nào được thêm vào cũng sẽ có quyền đọc
                                    if ($permission != 'read' && $assign_action === 'merge') {
                                        // Nếu old user có quyền & new k có
                                        if (!$new_parent) {

                                            // Grant access
                                            ApproveFlowUser::create([
                                                'user_id' => $newUserId,
                                                'approve_flow_id' => $current_approval_flow_crudie->id,
                                                'group_id' => $group_Id
                                            ]);
                                        }
                                    } elseif ($permission !== 'read' && $assign_action === 'overwrite') {

                                        // Revoke permission - Thu hồi quyền - newUser
                                        ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'group_id' => $group_Id, 'user_id' => $newUserId])->delete();

                                        if (!$new_parent) {
                                            // Grant access
                                            ApproveFlowUser::create([
                                                'user_id' => $newUserId,
                                                'approve_flow_id' => $current_approval_flow_crudie->id,
                                                'group_id' => $group_Id
                                            ]);
                                        }
                                    }
                                } elseif ($permission !== 'read' && $assign_action === 'overwrite') {
                                    // Revoke permission - Thu hồi quyền - newUser
                                    ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'group_id' => $group_Id, 'user_id' => $newUserId])->delete();
                                } elseif ($assign_action === 'keep') {
                                    // Revoke permission - Thu hồi quyền - oldUser
                                    ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'group_id' => $group_Id, 'user_id' => $oldUserId])->delete();
                                }
                            }

                            // Users containing parent_id = $oldUserId will be replaced with $newUserId
                            if ($flow->has_child > 0) {
                                foreach ($flow->approveFlowUsers as $child) {
                                    ApproveFlowUser::where(
                                        [
                                            'approve_flow_id' => $args['approve_flow_id'],
                                            'user_id' => $child->user_id,
                                            'group_id' => $group_Id
                                        ]
                                    )->update(['parent_id' => $newUserId]);
                                }
                            }

                            /*
                            * After revoking CRUDIE permissions, the user will be removed from the list
                            */
                            // Remove ApproveFlowUser - sub-flow
                            ApproveFlowUser::where(['approve_flow_id' => $args['approve_flow_id'], 'group_id' => $group_Id, 'user_id' => $oldUserId])->delete();

                            // refresh permission
                            $oldUser->refreshPermissions();
                            $newUser->refreshPermissions();
                            return true;
                        }
                    }
                }
            }
            return false;
        }
    }

    public function updateAssignApproveFlowUser($root, array $args)
    {
        $userId = $args['user_id'];
        $flow = ApproveFlow::query()->authUserAccessible()
            ->findOrFail($args['approve_flow_id']);

        $approveFlowUsers = $flow->approveFlowUsers->keyBy("user_id");

        // Check that the permission mode is enabled
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $flow->client_id)->first();

        // Check if the user is authorized in this
        if (!optional($clientWorkflowSetting)->advanced_permission_flow) {
            return false;
        } else {
            $user = User::where('id', $userId)->first();
            // CRUDIE access control based on permission
            foreach ($args['permission'] as $key => $value) {
                if ($key != 'read') {
                    $crudie = $flow->flow_name . "-" . $key;
                    $group_Id = $flow->group_id;
                    $AF_current = ApproveFlow::where(['flow_name' => $crudie, 'client_id' => $flow->client_id, 'group_id' => $group_Id])->first();
                    switch ($value) {
                        case true:
                            // Khởi tạo trên ApproveFlowUser
                            ApproveFlowUser::updateOrCreate(['user_id' => $userId, 'approve_flow_id' => $AF_current->id, 'group_id' => $group_Id]);
                            $user->forceAdvanceGivePermissionTo(false, $crudie, $group_Id);
                            break;
                        default:
                            // Xóa khỏi ApproveFlowUser
                            ApproveFlowUser::where(['user_id' => $userId, 'approve_flow_id' => $AF_current->id, 'group_id' => $group_Id])->delete();
                            $user->forceAdvanceGivePermissionTo(true, $crudie, $group_Id);
                            break;
                    }
                }
            }
            return true;
        }
    }

    public function unassignApproveFlowUser($root, array $args)
    {
        $userId = $args['user_id'];
        $flow = ApproveFlow::query()->authUserAccessible()
            ->findOrFail($args['approve_flow_id']);

        $users = $flow->approveFlowUsers->keyBy("user_id");

        // Check that the permission mode is enabled
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')->where('client_id', $flow->client_id)->first();

        if (!optional($clientWorkflowSetting)->advanced_permission_flow) {
            if (!$users->has($userId)) {
                return true;
            }
            $user = $users->get($userId);
            $user->delete();
            return true;
        } else {

            // Parent only
            $permission_list = collect(Constant::ADVANCED_PERMISSION_FLOW);
            $parentPermission =  $permission_list->contains('name', $flow->flow_name);

            if ($parentPermission) {
                $items =  $permission_list->firstWhere('name', $flow->flow_name);
                foreach ($items['sub'] as $value) {

                    // Remove ApproveFlowUser - sub-flow
                    $current_approval_flow = ApproveFlow::select('id')->where(['flow_name' => $value['name'], 'client_id' => $flow->client_id, 'group_id' => $flow->group_id])->first();
                    ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow->id, 'user_id' => $userId, 'group_id' => $flow->group_id])->delete();

                    foreach ($value['permission'] as $permission) {
                        // Remove ApproveFlowUser - crudie
                        $current_approval_flow_crudie = ApproveFlow::select('id')->where(['flow_name' => $value['name'] . '-' . $permission, 'client_id' => $flow->client_id, 'group_id' => $flow->group_id])->first();
                        ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'user_id' => $userId])->delete();
                    }
                }
            } else {

                foreach ($permission_list as $cate) {
                    foreach ($cate['sub'] as $sub) {
                        if ($sub['name'] === $flow->flow_name) {
                            foreach ($sub['permission'] as $permission) {
                                // Remove ApproveFlowUser - crudie
                                $current_approval_flow_crudie = ApproveFlow::select('id')->where(['flow_name' => $flow->flow_name . '-' . $permission, 'client_id' => $flow->client_id, 'group_id' => $flow->group_id])->first();
                                ApproveFlowUser::where(['approve_flow_id' => $current_approval_flow_crudie->id, 'user_id' => $userId, 'group_id' => $flow->group_id])->delete();
                            }
                            break;
                        }
                    }
                }
            }

            // Remove Parent
            ApproveFlowUser::where(['approve_flow_id' => $args['approve_flow_id'], 'user_id' => $userId, 'group_id' => $flow->group_id])->delete();

            // refresh permission
            User::find($userId)->refreshPermissions();

            if (!$users->has($userId)) {
                return true;
            }
            $user = $users->get($userId);
            $user->delete();
            return true;
        }
    }

    public function transferApproveFlowUser($root, array $args)
    {
        $srcUserId = $args["src_user_id"];
        $targetUserId = $args["target_user_id"];

        /** @var ApproveFlow $flow */
        $flow = ApproveFlow::query()->authUserAccessible()
            ->findOrFail($args['approve_flow_id']);

        /** @var Collection $users */
        $users = $flow->approveFlowUsers->keyBy("user_id");
        if (!$users->has($srcUserId)) {
            logger(__METHOD__ . ": Src user is not found in flow");
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]), ErrorCode::ERR0003);
        }

        if (!$users->has($targetUserId)) {
            logger(__METHOD__ . ": Target user is not found in flow");
            throw new HumanErrorException(__('error.not_found', ['name' => __('employee')]), ErrorCode::ERR0003);
        }

        Approve::query()
            ->where('client_id', $flow->client_id)
            ->where('type', $flow->flow_name)
            ->where('step', $flow->step)
            ->where('assignee_id', $srcUserId)
            ->update([
                'assignee_id' => $targetUserId,
            ]);


        return true;
    }
}
