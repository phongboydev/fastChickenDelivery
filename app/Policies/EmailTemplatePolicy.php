<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class EmailTemplatePolicy
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
    public function view(User $user, EmailTemplate $emailTemplate)
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
    public function create(User $user)
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
    public function update(User $user, EmailTemplate $emailTemplate)
    {
        if (!$user->isInternalUser()) {
            return false;
        } else {

            return true;
        }
    }

    /**
     * Determine whether the user can delete the formula.
     *
     * @param  User  $user
     * @param  \App\Formula  $formula
     *
     * @return mixed
     */
    public function delete(User $user, EmailTemplate $emailTemplate)
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
    public function restore(User $user, EmailTemplate $emailTemplate)
    {
        //
    }
}
