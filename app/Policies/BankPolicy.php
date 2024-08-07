<?php

namespace App\Policies;

use App\Models\Bank;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
class BankPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, Bank $bank)
    {
        //
    }

    public function create(User $user, array $injected)
    {
      if( $user->isInternalUser() )
      {
        return $user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients');
      }else{
        return false;
      }
    }

    public function update(User $user, Bank $bank)
    {
      if( $user->isInternalUser() )
      {
        return $user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients');
      }else{
        return false;
      }
    }

    public function delete(User $user, Bank $bank)
    {
      if( $user->isInternalUser() )
      {
        return $user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients');
      }else{
        return false;
      }
    }
}
