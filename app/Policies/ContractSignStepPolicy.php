<?php

namespace App\Policies;

use App\Models\ContractSignStep;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContractSignStepPolicy
{

    use HandlesAuthorization;

    public function __construct()
    {
        //
    }

    public function viewAny(User $user): bool
    {
        //
    }

    public function view(User $user, ContractSignStep $contractSignStep): bool
    {
        //
    }

    public function create(User $user): bool
    {
        //
    }

    public function update(User $user, ContractSignStep $contractSignStep): bool
    {
        //
    }

    public function delete(User $user, ContractSignStep $contractSignStep): bool
    {
        //
    }

    public function restore(User $user, ContractSignStep $contractSignStep): bool
    {
        //
    }

    public function forceDelete(User $user, ContractSignStep $contractSignStep): bool
    {
        //
    }
}
