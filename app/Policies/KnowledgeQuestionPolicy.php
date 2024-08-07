<?php

namespace App\Policies;

use App\Models\KnowledgeQuestion;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class KnowledgeQuestionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
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
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param User $model
     *
     * @return mixed
     */
    public function view(User $user, User $model)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $data)
    {
        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return false;
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_DIRECTOR:
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                default:
                    return true;
            }
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User $user
     * @param  User $model
     *
     * @return mixed
     */
    public function update(User $user, KnowledgeQuestion $model)
    {
        return $this->create($user, $model->toArray());
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User  $user
     * @param  User $model
     *
     * @return mixed
     */
    public function delete(User $user, KnowledgeQuestion $model)
    {
        return $this->create($user, $model->toArray());
    }

}
