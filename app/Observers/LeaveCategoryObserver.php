<?php

namespace App\Observers;

use App\Exceptions\CustomException;
use App\Jobs\CreateOrUpdateLeaveHoursOfClientEmployee;
use App\Models\LeaveCategory;
use App\Support\ErrorCode;
use Carbon\Carbon;

class LeaveCategoryObserver
{

    /**
     * Handle the LeaveCategory "creating" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     * @throws CustomException
     */
    public function creating(LeaveCategory $leaveCategory)
    {
        // You cannot create a leave schedule in the past
        $currentYearMonth = Carbon::now()->format('Y-m');
        $startDate = Carbon::parse($leaveCategory['start_date']);
        $endDate = Carbon::parse($leaveCategory['end_date']);
        $endDateAddYear = Carbon::parse($leaveCategory['end_date'])->addYear();

        // if ($startDate->format('Y-m') < $currentYearMonth || $endDate->format('Y-m') < $currentYearMonth) {
        //     throw new CustomException("You cannot create a leave schedule in the past.", 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        // }

        // entitlement_next_year_effective_date must not be greater than end_date by 1 year.
        if (!empty($leaveCategory['entitlement_next_year_effective_date'])) {
            $entitlementNextYearEffectiveDate = Carbon::parse($leaveCategory['entitlement_next_year_effective_date']);
            if ($entitlementNextYearEffectiveDate->gt($endDateAddYear)) {
                throw new CustomException(__("leave_management.validate.entitlement_next_year_effective_date"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
            } elseif ($entitlementNextYearEffectiveDate->lt($endDate)) {
                throw new CustomException(__("leave_management.validate.entitlement_next_year_effective_date_gtl"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
            }
        }

        // Check for overlapping start_date & end_date with previous time ranges
        $overlappingLeaveCategory = LeaveCategory::where([
            'type' => $leaveCategory['type'],
            'sub_type' => $leaveCategory['sub_type'],
            'client_id' => $leaveCategory['client_id'],
        ])->where(function ($query) use ($leaveCategory) {
            $query->where(function ($query) use ($leaveCategory) {
                $query->where('start_date', '<=', $leaveCategory['start_date'])
                    ->where('end_date', '>=', $leaveCategory['start_date']);
            })->orWhere(function ($query) use ($leaveCategory) {
                $query->where('start_date', '<=', $leaveCategory['end_date'])
                    ->where('end_date', '>=', $leaveCategory['end_date']);
            });
        })->where('id', '!=', $leaveCategory['id'])->exists();

        if ($overlappingLeaveCategory) {
            throw new CustomException(__("leave_management.validate.you_cannot_create_a_leave_schedule_in_the_past"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }

        // Do not coincide with the other year
        $leaveCategoryExit = LeaveCategory::where([
            'type' => $leaveCategory['type'],
            'sub_type' => $leaveCategory['sub_type'],
            'client_id' => $leaveCategory['client_id'],
            'year' => $leaveCategory['year'],
        ])->where('id', '!=', $leaveCategory['id'])->exists();

        if ($leaveCategoryExit) {
            throw new CustomException(__("leave_category_exits"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }

        // Check if the duration is not greater than 12 months
        $durationInMonths = $startDate->diffInMonths($endDate);

        if ($durationInMonths > 12) {
            throw new CustomException(__("leave_management.validate.the_start_date_and_end_date"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }
    }

    /**
     * Handle the LeaveCategory "created" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function created(LeaveCategory $leaveCategory)
    {
        dispatch(new CreateOrUpdateLeaveHoursOfClientEmployee(['leave' => $leaveCategory, 'action' => 'create'], null));
    }

    /**
     * Handle the LeaveCategory "updated" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     * @throws HumanErrorException
     */
    public function updating(LeaveCategory $leaveCategory)
    {
        // You cannot update a leave schedule in the past
        $currentYearMonth = Carbon::now()->format('Y-m');
        $startDate = Carbon::parse($leaveCategory['start_date']);
        $endDate = Carbon::parse($leaveCategory['end_date']);

        if ($startDate->format('Y-m') < $currentYearMonth || $endDate->format('Y-m') < $currentYearMonth) {
            throw new CustomException(__("leave_management.validate.you_cannot_update_a_leave_schedule_in_the_past"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }

        // entitlement_next_year_effective_date must not be greater than end_date by 1 year.
        if (!empty($leaveCategory['entitlement_next_year_effective_date'])) {
            $entitlementNextYearEffectiveDate = Carbon::parse($leaveCategory['entitlement_next_year_effective_date']);
            $endDate = Carbon::parse($leaveCategory['end_date']);

            if ($entitlementNextYearEffectiveDate->greaterThan($endDate->addYear())) {
                throw new CustomException(__("leave_management.validate.entitlement_next_year_effective_date"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
            }
        }

        // Check for overlapping start_date & end_date with previous time ranges
        $overlappingLeaveCategory = LeaveCategory::where([
            'type' => $leaveCategory['type'],
            'sub_type' => $leaveCategory['sub_type'],
            'client_id' => $leaveCategory['client_id'],
        ])->where(function ($query) use ($leaveCategory) {
            $query->where(function ($query) use ($leaveCategory) {
                $query->where('start_date', '<=', $leaveCategory['start_date'])
                    ->where('end_date', '>=', $leaveCategory['start_date']);
            })->orWhere(function ($query) use ($leaveCategory) {
                $query->where('start_date', '<=', $leaveCategory['end_date'])
                    ->where('end_date', '>=', $leaveCategory['end_date']);
            });
        })->where('id', '!=', $leaveCategory['id'])->exists();

        if ($overlappingLeaveCategory) {
            throw new CustomException(__("leave_management.validate.the_start_date_and_end_date_overlap"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }

        // Do not coincide with the other year
        $leaveCategoryExit = LeaveCategory::where([
            'type' => $leaveCategory['type'],
            'sub_type' => $leaveCategory['sub_type'],
            'client_id' => $leaveCategory['client_id'],
            'year' => $leaveCategory['year'],
        ])->where('id', '!=', $leaveCategory['id'])->exists();

        if ($leaveCategoryExit) {
            throw new CustomException(__("leave_category_exits"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }

        // Check if the duration is not greater than 12 months
        $startDate = Carbon::parse($leaveCategory['start_date']);
        $endDate = Carbon::parse($leaveCategory['end_date']);
        $durationInMonths = $startDate->diffInMonths($endDate);

        if ($durationInMonths > 12) {
            throw new CustomException(__("leave_management.validate.the_start_date_and_end_date"), 'ValidationException', ErrorCode::ERR0004, [], "warning", "create");
        }
    }

    /**
     * Handle the LeaveCategory "updated" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function updated(LeaveCategory $leaveCategory)
    {
        if ($leaveCategory->isDirty()) {
            dispatch(new CreateOrUpdateLeaveHoursOfClientEmployee(['leave' => $leaveCategory, 'action' => 'update'], null));
        }
    }

    /**
     * Handle the LeaveCategory "deleting" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function deleting(LeaveCategory $leaveCategory)
    {
    }

    /**
     * Handle the LeaveCategory "deleted" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function deleted(LeaveCategory $leaveCategory)
    {
    }

    /**
     * Handle the LeaveCategory "restored" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function restored(LeaveCategory $leaveCategory)
    {
        //
    }

    /**
     * Handle the LeaveCategory "force deleted" event.
     *
     * @param LeaveCategory $leaveCategory
     * @return void
     */
    public function forceDeleted(LeaveCategory $leaveCategory)
    {
    }
}
