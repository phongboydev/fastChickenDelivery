<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\EmployeeTimesheetResolver;
use App\Models\ClientEmployee;
use App\Models\Timesheet;
use App\Models\WorkScheduleGroupTemplate;

class GetEmployeesTimesheetGridDataQuery
{

    use EmployeeTimesheetResolver;

    /**
     * @param  null  $_
     * @param  array{
     *      client_id: string,
     *      department_filter: string[],
     *      employee_filter: string,
     *      log_date: string,
     *      client_employee_ids: string[]
     * }  $args
     */
    public function __invoke($_, array $args)
    {
        $page = $args['page'] ?? 1;
        $perPage = $args['per_page'] ?? 20;
        if ($perPage > 50) {
            $perPage = 50; // hard upper limit to prevent abuse
        }
        $date = $args['log_date'];
        $argEmployeeIds = $args['client_employee_ids'] ?? null;
        $employeeFilter = $args['employee_filter'] ?? null;
        $departmentFilter = $args['department_filter'] ?? null;
        $user = auth()->user();
        // only internal user allowed to override client_id
        if ($user->is_internal) {
            $clientId = $args['client_id'];
        } else {
            $clientId = $user->client_id;
        }

        // fetch possible client employees
        $query = ClientEmployee::query()
            ->status()
            ->select(['id', 'work_schedule_group_template_id'])
            ->where('client_id', $clientId)
            ->when($employeeFilter, function ($query, $employeeFilter) {
                return $query->where('full_name', 'like', "%$employeeFilter%")
                    ->orWhere('code', 'like', "%$employeeFilter%");
            })
            ->when($departmentFilter, function ($query, $departmentFilter) {
                return $query->whereIn('client_department_id', $departmentFilter);
            })
            ->when($argEmployeeIds, function ($query, $argEmployeeIds) {
                return $query->whereIn('id', $argEmployeeIds);
            })
            ->orderBy('code');

        $paginator = $query->paginate($perPage, ['id', 'work_schedule_group_template_id'], 'page', $page);
        $employeeResult = $paginator->getCollection();

        // prefetch group template (for core_in, core_out)
        $wsgtIds = $employeeResult->pluck('work_schedule_group_template_id')->toArray();
        $wsgtMap = WorkScheduleGroupTemplate::query()
            ->select(['id', 'core_time_in', 'core_time_out'])
            ->whereIn('id', $wsgtIds)
            ->get()
            ->keyBy('id');

        $employeeIds = $employeeResult->pluck('id')->toArray();

        $result = $this->getTimesheetWorkSchedules($date, $date, $employeeIds);
        $clientEmployees = $result['clientEmployees'];
        $timesheets = $result['timesheets'];
        $schedules = $result['schedules'];

        $output = [];

        // transform the original paginator
        $paginator->transform(function ($item) use ($timesheets, $schedules, $wsgtMap, $clientEmployees) {
            $clientEmployee = $clientEmployees[$item->id];
            $item = [
                'client_employee_id' => $clientEmployee->id,
            ];
            $item['client_id'] = $clientEmployee->client_id;
            $item['full_name'] = $clientEmployee->full_name;
            $item['code'] = $clientEmployee->code;
            $item['user_id'] = $clientEmployee->user_id;
            $item['department'] = $clientEmployee->department;
            $item['position'] = $clientEmployee->position;
            $item['timesheets'] = $timesheets[$clientEmployee->id] ?? [];
            $item['schedules'] = $schedules[$clientEmployee->id] ?? [];
            $item['core_time_in'] = $wsgtMap[$clientEmployee->work_schedule_group_template_id]->core_time_in ?? null;
            $item['core_time_out'] = $wsgtMap[$clientEmployee->work_schedule_group_template_id]->core_time_out ?? null;
            return $item;
        });
        // foreach ($clientEmployees as $clientEmployee) {
        //     $item = [
        //         'client_employee_id' => $clientEmployee->id,
        //     ];
        //     $item['client_id'] = $clientEmployee->client_id;
        //     $item['full_name'] = $clientEmployee->full_name;
        //     $item['code'] = $clientEmployee->code;
        //     $item['user_id'] = $clientEmployee->user_id;
        //     $item['department'] = $clientEmployee->department;
        //     $item['position'] = $clientEmployee->position;
        //     $item['timesheets'] = $timesheets[$clientEmployee->id] ?? [];
        //     $item['schedules'] = $schedules[$clientEmployee->id] ?? [];
        //     $item['core_time_in'] = $wsgtMap[$clientEmployee->work_schedule_group_template_id]->core_time_in ?? null;
        //     $item['core_time_out'] = $wsgtMap[$clientEmployee->work_schedule_group_template_id]->core_time_out ?? null;
        //     $output[] = $item;
        // }

        return $paginator->toArray();
    }

    private function buildGroupedResult($result)
    {
        $output = [];
        foreach ($result as $key => $data) {
            $output[] = [
                'client_employee_id' => $key,
                '',
            ];
        }
    }
}
