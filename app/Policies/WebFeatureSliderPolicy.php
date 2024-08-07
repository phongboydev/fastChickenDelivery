<?php

namespace App\Policies;

use App\Models\WebFeatureSlider;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Auth;

class WebFeatureSliderPolicy
{
    use HandlesAuthorization;

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
     * @param  \App\Models\WebFeatureSlider  $webFeatureSlider
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, WebFeatureSlider $webFeatureSlider)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        //
        return Auth::user()->isInternalUser();
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\WebFeatureSlider  $webFeatureSlider
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update()
    {
        //
        return Auth::user()->isInternalUser();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\WebFeatureSlider  $webFeatureSlider
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete()
    {
        //
        return Auth::user()->isInternalUser();
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\WebFeatureSlider  $webFeatureSlider
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, WebFeatureSlider $webFeatureSlider)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\WebFeatureSlider  $webFeatureSlider
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, WebFeatureSlider $webFeatureSlider)
    {
        //
        return Auth::user()->isInternalUser();
    }
    public function upload(User $user, WebFeatureSlider $slider): bool
    {
        return Auth::user()->isInternalUser();
    }
}
