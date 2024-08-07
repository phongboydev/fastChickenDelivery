<?php

namespace App\Policies;

use App\Models\TimeSheetEmployeeExport;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeSheetEmployeeExportPolicy
{

    use HandlesAuthorization;

    public function __construct()
    {
        //
    }

    public function update(User $user, TimeSheetEmployeeExport $timeSheetEmployeeExport): bool
    {
        return $this->checkPermission($user, $timeSheetEmployeeExport);
    }

    public function delete(User $user, TimeSheetEmployeeExport $timeSheetEmployeeExport): bool
    {
        return $this->checkPermission($user, $timeSheetEmployeeExport);
    }

    private function checkPermission(User $user, TimeSheetEmployeeExport $timeSheetEmployeeExport): bool
    {
        return $timeSheetEmployeeExport->user_id == $user->id;
    }
}
