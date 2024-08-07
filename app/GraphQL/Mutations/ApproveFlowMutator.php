<?php

namespace App\GraphQL\Mutations;

use App\Support\WorktimeRegisterHelper;
use App\User;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

use App\Jobs\ConfirmApproveJob;

use App\Support\Constant;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\Approve;
use App\Models\ApproveGroup;
use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Notifications\ApproveNotification;

class ApproveFlowMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $action = $args['status'];
        $approveId = $args['approve_id'];

        $comment = isset($args['comment']) ? $args['comment'] : '';
        $creatorId = Auth::user()->id;
        $reviewerId = isset($args['reviewer_id']) && $args['reviewer_id'] ? $args['reviewer_id'] : false;
        $nextStep = isset($args['next_step']) ? $args['next_step'] : '';

        logger('ApproveFlowMutator@__invoke begin ' . $approveId, [$action, $creatorId, $reviewerId]);

        $approve = Approve::where('id', $approveId)->first();
        // Validate
        WorktimeRegisterHelper::validateApplication([$approve]);

        if (!$approve || ($approve->processing_state !== null && $approve->processing_state != 'fail')) return false;

        $approve->processing_state = 'processing';
        $approve->source = isset($args['source']) ?? $args['source'];
        $approve->saveQuietly();

        logger('ApproveFlowMutator@__invoke dispatch ' . $creatorId, [$approve->client_id, $action, $approveId, $reviewerId]);

        ConfirmApproveJob::dispatchSync($action, $approveId, $comment, $creatorId, $reviewerId, $nextStep);

        return true;
    }

    public function confirmMultipleApproves($root, array $args)
    {
        $action = $args['status'];
        $approveIds = $args['approves'];
        $comment = isset($args['comment']) ? $args['comment'] : '';
        $user = Auth::user();
        $creatorId = $user->id;
        $reviewerId = isset($args['reviewer_id']) && $args['reviewer_id'] ? $args['reviewer_id'] : false;
        $approves = Approve::whereIn('id', $approveIds)->get();
        if ($approves->isEmpty()) return false;
        // Validate
        WorktimeRegisterHelper::validateApplication($approves);
        // Get setting
        $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $user->client_id)->first(['advanced_approval_flow']);
        $isAdvanced = false;
        if($clientWorkflowSetting && $clientWorkflowSetting->advanced_approval_flow && in_array($approves[0]->type, Constant::TYPE_ADVANCED_APPROVE)) {
            $isAdvanced = true;
        }

        foreach ($approves as $ap) {
            if ($ap->processing_state === null) {
                logger('ApproveFlowMutator@confirmMultipleApproves begin ' . $ap->id, [$ap->client_id, $action, $creatorId, $reviewerId]);
                $ap->processing_state = 'processing';
                $ap->saveQuietly();
                $nextStep = null;
                if ($isAdvanced) {
                    $nextStep = 0;
                    $flowName = $ap->flow_type ?? $ap->type;
                    $clientEmployeeGroupId = $ap->client_employee_group_id;
                    $stepApproveFlow = ApproveFlow::where('flow_name', $flowName)
                        ->where('group_id', $clientEmployeeGroupId)
                        ->whereHas('approveFlowUsers', function ($query) use ($reviewerId) {
                            $query->where('user_id', $reviewerId);
                        })->get()->pluck('step')->toArray();
                    sort($stepApproveFlow);
                    foreach ($stepApproveFlow as $step) {
                        if ($ap->step < $step) {
                            $nextStep = $step;
                            break;
                        }
                    }
                }
                dispatch(new ConfirmApproveJob($action, $ap->id, $comment, $creatorId, $reviewerId, $nextStep));
            }
        }

        return true;
    }

    public function getHistory($root, array $args)
    {
        $approves = Approve::where('approve_group_id', $args['approve_group_id'])->orderBy('step', 'ASC')->get();

        if ($approves->isNotEmpty()) {

            $hasApproves = $approves->where('assignee_id', Auth::user()->id);

            if ($hasApproves) {
                return $approves;
            } else {
                return [];
            }
        } else {
            return [];
        }
    }

    public function getApproveFlowForMyGroup($root, array $args)
    {

        $user = Auth::user();
        $groups = ['0'];

        $approveFlows = ApproveFlow::where('flow_name', $args['flow_name'])
            ->with('approveFlowUsers')
            ->when(!empty($args['client_employee_id']), function ($query) use ($args, $user, $groups) {
                $clientEmployee = ClientEmployee::find($args['client_employee_id']);
                // Check permission
                if (!$clientEmployee->user->isInternalUser() &&
                $user->clientEmployee->id !== $args['client_employee_id'] &&
                $clientEmployee->user->checkHavePermission(['manage-employee', 'manage-timesheet'], ['advanced-manage-employee-list-read'], $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                    $clientEmployeeGroupAssignment = $clientEmployee->clientEmployeeGroupAssignment;
                    $groups =  array_merge($groups, $clientEmployeeGroupAssignment->pluck('client_employee_group_id')->all());
                    return $query->where('client_id', $clientEmployee->client_id)
                        ->whereIn('group_id', $groups);
                } else {
                    return $query;
                }
            })
            ->when(empty($args['client_employee_id']) && !$user->isInternalUser(), function ($query) use ($user, $groups) {
                $clientEmployeeGroupAssignment = $user->clientEmployee->clientEmployeeGroupAssignment;
                $groups =  array_merge($groups, $clientEmployeeGroupAssignment->pluck('client_employee_group_id')->all());
                return $query->where('client_id', $user->client_id)
                    ->whereIn('group_id', $groups);
            })
            ->get();

        return $approveFlows;
    }

    public function getApproveFlow($root, array $args)
    {
        $clientId = $args['client_id'];
        $flowName = $args['flow_name'];
        $groupId = $args['group_id'];
        $subflowsExistsUsers = false;
        $data = null;

        $user = Auth::user();
        $role = $user->getRole();
        $clientWorkflowSetting = ClientWorkflowSetting::select('advanced_permission_flow')
            ->where('client_id', $clientId)
            ->first();

        if (
            (!$user->isInternalUser() && $clientId === $user->client_id) ||
            ($user->isInternalUser() && ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($clientId))) &&
            $clientWorkflowSetting && $clientWorkflowSetting->advanced_permission_flow
        ) {
            $approveFlow = ApproveFlow::where('client_id', $clientId)
                ->where('flow_name', $flowName)
                ->where('group_id', $groupId);

            $data = $approveFlow->first();

            // Parent only
            $permissionList = collect(Constant::ADVANCED_PERMISSION_FLOW);
            $parentPermission = $permissionList->contains('name', $flowName);

            if ($data && $parentPermission) {
                $items = $permissionList->firstWhere('name', $flowName);

                foreach ($items['sub'] as $item) {
                    $currentApprovalFlowName = $item['name'];

                    $currentApprovalFlow = ApproveFlow::where([
                        'flow_name' => $currentApprovalFlowName,
                        'client_id' => $clientId,
                        'group_id' => $groupId
                    ])->with(['approveFlowUsers' => function ($q) {
                        $q->whereNull('approve_flow_users.parent_id')
                            ->withCount('children');
                    }])->first();

                    if ($currentApprovalFlow) {
                        $subflowsExistsUsers = $currentApprovalFlow->approveFlowUsers->contains('children_count', '>', 0);
                        if ($subflowsExistsUsers) {
                            break;
                        }
                    }
                }
            }

            $data['subflows_exists_users'] = $subflowsExistsUsers;
        }

        return $data;
    }
}
