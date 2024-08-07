<?php

namespace App\Policies;

use App\Models\TimesheetShift;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimesheetShiftPolicy
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

    public function view(User $user, TimesheetShift $timesheetShift): bool
    {
        //
    }

    public function create(User $user, array $injected): bool
    {
        return $this->_checkPermission($user, $injected, ['manage-timesheet'], ['advanced-manage-timesheet-timesheet-shift-create']);
    }

    public function update(User $user, TimesheetShift $timesheetShift, array $injected): bool
    {
        return $this->_checkPermission($user, $timesheetShift->toArray(), ['manage-timesheet'], ['advanced-manage-timesheet-timesheet-shift-update']);
    }

    public function delete(User $user, TimesheetShift $timesheetShift): bool
    {
        return $this->_checkPermission($user, $timesheetShift->toArray(), ['manage-timesheet'], ['advanced-manage-timesheet-timesheet-shift-delete']);
    }

    public function restore(User $user, TimesheetShift $timesheetShift): bool
    {
        //
    }

    public function forceDelete(User $user, TimesheetShift $timesheetShift): bool
    {
        return $this->_checkPermission($user, $timesheetShift->toArray(), ['manage-timesheet'], ['advanced-manage-timesheet-timesheet-shift-delete']);
    }

    private function _checkPermission(User $user, $input, $normalPermission, $advancedPermission): bool
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $input['client_id']) {
                logger(self::class . ": ClientID not match");
                return false;
            }
            // Check permission
            return $user->checkHavePermission($normalPermission, $advancedPermission, $user->getSettingAdvancedPermissionFlow($user->client_id));
        } else {
            return $user->checkHavePermission([], [], false, $input['client_id']);
        }
    }
}
