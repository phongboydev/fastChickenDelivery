<?php

namespace App\Policies;

use App\Models\ClientEmployee;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetShiftMapping;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TimeSheetShiftMappingPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the user can update.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function update(User $user, array $injected)
    {
        $timesheetShiftMapping = TimesheetShiftMapping::with('timesheet.clientEmployee')->find($injected['id']);
        $clientWorkflowSetting = ClientWorkflowSetting::select('enable_timesheet_input')->where('client_id', '=', $user->client_id)->first();

        if (!$user->isInternalUser()) {
            if (!$timesheetShiftMapping || !$timesheetShiftMapping->timesheet) {
                return false;
            }
            if (!$clientWorkflowSetting->enable_timesheet_input) {
                return false;
            }
            if ($user->clientEmployee->id == $timesheetShiftMapping->timesheet->clientEmployee->id) {
                return true;
            }
            if ($user->hasPermissionTo("manage-timesheet")) {
                return true;
            }
            return false;
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_LEADER:
                    if ($user->iGlocalEmployee->isAssignedFor($timesheetShiftMapping->timesheet->clientEmployee->client_id)) {
                        return true;
                    }
                    return false;
                default:
                    return false;
            }
        }
        return false;
    }
}
