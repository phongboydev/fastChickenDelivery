<?php

namespace App\Policies;

use App\Models\PowerBiReport;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PowerBiReportPolicy
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

    public function view(User $user, PowerBiReport $powerBiReport): bool
    {
        //
    }

    public function create(User $user): bool
    {
        //
    }

    public function update(User $user, PowerBiReport $powerBiReport): bool
    {
        //
    }

    public function delete(User $user, PowerBiReport $powerBiReport): bool
    {
        //
    }

    public function restore(User $user, PowerBiReport $powerBiReport): bool
    {
        //
    }

    public function forceDelete(User $user, PowerBiReport $powerBiReport): bool
    {
        //
    }
}
