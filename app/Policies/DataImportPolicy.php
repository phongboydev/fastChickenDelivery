<?php

namespace App\Policies;

use App\Models\DataImport;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class DataImportPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any formulas.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the formula.
     *
     * @param User          $user
     * @param  \App\Formula $formula
     *
     * @return mixed
     */
    public function view(User $user, DataImport $dataImport)
    {
        //
    }

    /**
     * Determine whether the user can create formulas.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {

            return true;
        }
    }

    /**
     * Determine whether the user can update the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function update(User $user, DataImport $dataImport)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function delete(User $user, DataImport $dataImport)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {

            return true;
        }
    }

    /**
     * Determine whether the user can restore the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function restore(User $user, DataImport $dataImport)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function forceDelete(User $user, DataImport $dataImport)
    {
        //
    }
}
