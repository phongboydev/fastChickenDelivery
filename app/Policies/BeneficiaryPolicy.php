<?php

namespace App\Policies;

use App\User;
use App\Models\Beneficiary;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class BeneficiaryPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-payment-request';

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Beneficiary  $beneficiary
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, array $injected)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Beneficiary  $beneficiary
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Beneficiary $beneficiary)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Beneficiary  $beneficiary
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Beneficiary $beneficiary)
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Beneficiary  $beneficiary
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Beneficiary $beneficiary)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Beneficiary  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Beneficiary $beneficiary)
    {
        //
    }
}
