<?php

namespace App\GraphQL\Mutations;

use App\Jobs\CreateOrUpdateLeaveHoursOfClientEmployee;
use App\Models\ClientEmployeeLeaveManagement;
use App\Models\LeaveCategory;
use App\Support\ErrorCode;
use App\Exceptions\HumanErrorException;
use App\Models\ClientEmployee;

class ClientEmployeeLeaveManagementMutator
{
    public function clientEmployeeLeaveManagements($root, array $args)
    {
        $query = ClientEmployeeLeaveManagement::whereHas('leaveCategory');
        if (isset($args['employee_filter'])) {
            $employeeFilter = $args['employee_filter'];
            $query->whereHas('clientEmployee', function ($subQuery) use ($employeeFilter) {
                $subQuery->where(function ($q) use ($employeeFilter) {
                    $q->where('full_name', 'LIKE', '%' . $employeeFilter . '%')
                        ->orWhere('code', 'LIKE', '%' . $employeeFilter . '%');
                });
            });
        }
        return $query;
    }

    /**
     * @throws HumanErrorException
     */
    public function createClientEmployeeLeaveManagement($root, array $args)
    {
        $clientEmployeeIds = $args['client_employee_ids'];
        $leaveCategoryId = $args['leave_category_id'];
        $leaveCategory = LeaveCategory::find($leaveCategoryId);

        if ($leaveCategory) {
            // Check if client employee leave management already exists
            $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::where('leave_category_id', $leaveCategoryId)
                ->whereIn('client_employee_id', $clientEmployeeIds)
                ->first();

            if ($clientEmployeeLeaveManagement) {
                throw new HumanErrorException(__("importing.undefined"), ErrorCode::ERR0004);
            }
            CreateOrUpdateLeaveHoursOfClientEmployee::dispatchSync(['leave' => $leaveCategory, 'action' => 'create', 'client_employee_ids' => $clientEmployeeIds], null);
            return true;
        }

        return false;
    }

    public function clientEmployeesNotInLeaveManagement($root, array $args)
    {
        $leaveCategoryId = $args['leave_category_id'];
        $clientId = auth()->user()->client_id;
        $leaveCategory = LeaveCategory::where(['id' => $leaveCategoryId, 'client_id' => $clientId])->first();

        if ($leaveCategory) {
            $existingEmployeeIds = ClientEmployeeLeaveManagement::where('leave_category_id', $leaveCategoryId)
                ->pluck('client_employee_id');

            $clientEmployeesNotInLeaveManagement = ClientEmployee::where('client_id', $clientId)
                ->whereNotIn('id', $existingEmployeeIds)
                ->whereNull('quitted_at')
                ->get();

            return $clientEmployeesNotInLeaveManagement;
        }

        return [];
    }
}
