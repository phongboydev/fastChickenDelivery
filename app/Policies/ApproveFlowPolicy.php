<?php

namespace App\Policies;

use App\Models\ApproveFlow;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApproveFlowPolicy
{
    use HandlesAuthorization;

    protected static $INTERNAL_FLOWS_PERMISSIONS = [
        'INTERNAL_MANAGE_CALCULATION', 'manage_iglocal_user', 'manage_assignement', 'manage_clients', 'manage_export_template'
    ];

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, ApproveFlow $approveFlow)
    {
        //
    }

    public function create(User $user, array $injected)
    {
        if ($user->isInternalUser()) {
            if (self::isInternalFlowsPermissions($injected['flow_name'])) {
                return $user->iGlocalEmployee->role == 'director';
            } elseif ($user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function update(User $user, ApproveFlow $approveFlow)
    {
        if ($user->isInternalUser()) {
            if (self::isInternalFlowsPermissions($approveFlow->flow_name)) {
                return $user->iGlocalEmployee->role == 'director';
            } elseif ($user->hasDirectPermission('manage_clients') || $user->iGlocalEmployee->isAssignedFor($approveFlow->client_id)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function delete(User $user, ApproveFlow $approveFlow)
    {
        if ($user->isInternalUser()) {
            if (self::isInternalFlowsPermissions($approveFlow->flow_name)) {
                return $user->iGlocalEmployee->role == 'director';
            } elseif ($user->hasDirectPermission('manage_clients') ||  $user->iGlocalEmployee->isAssignedFor($approveFlow->client_id)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function isInternalFlowsPermissions($flowName)
    {
        return in_array($flowName, self::$INTERNAL_FLOWS_PERMISSIONS);
    }
}
