<?php

namespace App\Policies;

use App\Models\ApproveFlowUser;
use App\Models\ApproveFlow;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\ApproveFlowPolicy;

class ApproveFlowUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, ApproveFlowUser $approveFlow)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        $approveFlow = ApproveFlow::select('*')->where('approve_flow_id', $injected['approve_flow_id'])->first();

        if(empty($approveFlow)) return false;

        $approveFlowPolicy = new ApproveFlowPolicy();

        return $approveFlowPolicy->create($user, ['client_id' => $approveFlow->client_id, 'flow_name' => $approveFlow->flow_name]);
    }


    public function update(User $user, ApproveFlowUser $approveFlowUser)
    {
        $approveFlow = ApproveFlow::select('*')->where('approve_flow_id', $approveFlowUser->approve_flow_id)->first();

        if(empty($approveFlow)) return false;

        $approveFlowPolicy = new ApproveFlowPolicy();

        return $approveFlowPolicy->update($user, $approveFlow);
    }

    public function delete(User $user, ApproveFlowUser $approveFlowUser)
    {
        $approveFlow = ApproveFlow::select('*')->where('approve_flow_id', $approveFlowUser->approve_flow_id)->first();

        if(empty($approveFlow)) return false;
        
        $approveFlowPolicy = new ApproveFlowPolicy();

        return $approveFlowPolicy->update($user, $approveFlow);
    }

}
