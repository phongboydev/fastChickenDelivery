<?php

namespace App\Policies;

use App\Models\ProvinceHospital;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;
class ProvinceHospitalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        //
    }

    public function view(User $user, ProvinceHospital $rovinceHospital)
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

    public function update(User $user, ProvinceHospital $rovinceHospital)
    {
      if( $user->isInternalUser() )
      {
        return $user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients');
      }else{
        return false;
      }
    }

    public function delete(User $user, ProvinceHospital $rovinceHospital)
    {
      if( $user->isInternalUser() )
      {
        return $user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients');
      }else{
        return false;
      }
    }
}
