<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Constant;

class RecruitmentProcessAssignment extends Model
{

  protected $fillable = [
    'recruitment_process_id',
    'client_employee_id',
    'created_at',
    'updated_at'
  ];

  public function clientEmployee()
  {
    return $this->belongsTo('App\Models\ClientEmployee');
  }

  public function scopeAuthUserAccessible($query)
  {
    // Get User from token
    /** @var User $user */
    $user = auth()->user();
    $role = $user->getRole();

    if (!$user->isInternalUser()) {
      switch ($role) {
        case Constant::ROLE_CLIENT_MANAGER:
        case Constant::ROLE_CLIENT_HR:
          return $this->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
            $clientEmployee->where('client_id', $user->client_id);
          });
        default:
          if ($user->hasAnyPermission(['manage-jobboard'])) {
            return $this->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
              $clientEmployee->where('client_id', $user->client_id);
            });
          }
          return $this->where('client_employee_id', $user->clientEmployee->id);
      }
    } else {
      switch ($role) {
        case Constant::ROLE_INTERNAL_LEADER:
        case Constant::ROLE_INTERNAL_STAFF:
          return $query->belongToClientAssignedTo($user->iGlocalEmployee, 'clientEmployee');
        case Constant::ROLE_INTERNAL_DIRECTOR:
          return $query;
      }
    }
  }
}
