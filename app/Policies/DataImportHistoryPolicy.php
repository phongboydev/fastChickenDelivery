<?php

namespace App\Policies;

use App\Models\DataImportHistory;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class DataImportHistoryPolicy
{
    use HandlesAuthorization;

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
    public function view(User $user, DataImportHistory $dataImportHistory)
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
        return true;
    }

    /**
     * Determine whether the user can update the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function update(User $user, DataImportHistory $importDataHistory)
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
    public function delete(User $user, DataImportHistory $importDataHistory)
    {
        return false;
    }

    /**
     * Determine whether the user can restore the iglocal employee.
     *
     * @param  User  $user
     * @param  \App\IglocalEmployee  $iglocalEmployee
     *
     * @return mixed
     */
    public function restore(User $user, DataImportHistory $importDataHistory)
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
    public function forceDelete(User $user, DataImportHistory $importDataHistory)
    {
        //
    }

    public function upload(User $user, DataImportHistory $importDataHistory) {
        return true;
    }
}
