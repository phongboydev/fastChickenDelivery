<?php

namespace App\Policies;

use App\Models\JobboardApplication;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class JobboardApplicationPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-jobboard';

    /**
     * Determine whether the user can view any iglocal employees.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
    }

    /**
     * Determine whether the user can view the iglocal employee.
     *
     * @param User                  $user
     * @param  \App\IglocalEmployee $iglocalEmployee
     *
     * @return mixed
     */
    public function view(User $user, JobboardApplication $jobboardApplication)
    {
        //
    }

    /**
     * Determine whether the user can create iglocal employees.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
    }

    /**
     * Determine whether the user can update the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function update(User $user, JobboardApplication $jobboardApplication)
    {
        return true;
    }
    /**
     * Determine whether the user can delete the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function delete(User $user, JobboardApplication $jobboardApplication)
    {
        $client_id = $jobboardApplication->client_id;

        if (!$user->isInternalUser()) {
            if ($user->client_id == $client_id && $user->hasDirectPermission($this->managerPermission)) {
                return true;
            }

            return false;
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if (!empty($client_id)) {
                        if ($user->iGlocalEmployee->isAssignedFor($client_id)) {
                            return true;
                        }
                    }
                    return false;
                default:
                    return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function restore(User $user, JobboardApplication $iglocalEmployee)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function forceDelete(User $user, JobboardApplication $iglocalEmployee)
    {
        //
    }

    public function upload(User $user, JobboardApplication $model)
    {
        return true;
    }
}
