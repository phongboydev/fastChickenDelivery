<?php

namespace App\GraphQL\Mutations;

use App\Models\Client;
use App\Models\ClientWorkflowSetting;
use App\Models\TimesheetShift;
use App\Support\ApproveObserverTrait;
use App\Support\WorkTimeRegisterPeriodHelper;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use App\Exceptions\HumanErrorException;
use Illuminate\Support\Carbon;
use App\Models\Approve;
use App\Models\Timesheet;
use App\Models\WorktimeRegister;
use App\Models\WorkTimeRegisterPeriod;
use App\Models\ClientEmployee;
use Exception;
use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Boundaries;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\WorktimeRegisterExport;
use App\Models\WorktimeRegisterCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\WorktimeRegisterHelper;
use App\Support\PeriodHelper;
use App\Support\Constant;
use App\Support\LeaveHelper;

class WorktimeRegisterMutator
{
    use ApproveObserverTrait;
    public function worktimeRegisters($root, array $args)
    {
        if (isset($args['has_fee'])) {
            $query = WorktimeRegister::with('periods')->whereHas('periods', function ($q) use ($args) {
                if (isset($args['has_fee'])) {
                    $q->where('work_time_register_periods.has_fee', $args['has_fee']);
                }
            });
        } else {
            $query = WorktimeRegister::select(
                'work_time_registers.*',
                'client_employees.full_name'
            )
                ->join('client_employees', 'client_employees.id', 'work_time_registers.client_employee_id');
        }

        if (isset($args['status'])) {
            switch ($args['status']) {
                case Constant::WAIT_CANCEL_APPROVE:
                    $query = $query->whereHas('periods', function ($subQuery) {
                        $subQuery->where('is_cancellation_approval_pending', 1);
                    });
                    break;
                default:
                    $query = $query->where('work_time_registers.status', $args['status'])
                        ->whereDoesntHave('periods', function ($subQuery) {
                            $subQuery->where('is_cancellation_approval_pending', 1);
                        });
                    break;
            }
        }

        // Filter by range day
        if (!empty($args['start_date']) && !empty($args['end_date'])) {
            $query->whereHas('workTimeRegisterPeriod', function ($subQuery) use ($args) {
                $subQuery->whereBetween('date_time_register', [$args['start_date'], $args['end_date']]);
            });
        }

        return $query;
    }

    public function workTimeRegistersByMultipleFilter($root, array $args)
    {
        if (isset($args['has_fee'])) {
            $query = WorktimeRegister::with('periods')->whereHas('periods', function ($q) use ($args) {
                if (isset($args['has_fee'])) {
                    $q->where('work_time_register_periods.has_fee', $args['has_fee']);
                }
            });
        } else {
            $query = WorktimeRegister::select(
                'work_time_registers.*',
                'client_employees.full_name'
            )
                ->join('client_employees', 'client_employees.id', 'work_time_registers.client_employee_id');
        }

        if (!empty($args['status'])) {
            $query->where(function ($query) use ($args) {
                if (in_array(Constant::WAIT_CANCEL_APPROVE, $args['status'])) {
                    $query->where(function ($subQuery1) {
                        $subQuery1->whereHas('periods', function ($subQuery2) {
                            $subQuery2->where('is_cancellation_approval_pending', 1);
                        });
                    });
                }

                $args['status'] = array_diff($args['status'], [Constant::WAIT_CANCEL_APPROVE]);
                if (!empty($args['status'])) {
                    $query->orWhere(function ($subQuery1) use ($args) {
                        $subQuery1->whereIn('work_time_registers.status', $args['status'])
                            ->whereDoesntHave('periods', function ($subQuery2) {
                                $subQuery2->where('is_cancellation_approval_pending', 1);
                            });
                    });
                }
            });
        }

        // Filter by range day
        if (!empty($args['start_date']) && !empty($args['end_date'])) {
            $query->whereHas('workTimeRegisterPeriod', function ($subQuery) use ($args) {
                $subQuery->whereBetween('date_time_register', [$args['start_date'], $args['end_date']]);
            });
        }

        // Filter by employee filter
        if (!empty($args['employee_filter']) || !empty($args['department_filter'])) {
            $query->whereHas('clientEmployee', function ($subQuery) use ($args) {
                if (!empty($args['employee_filter'])) {
                    $subQuery->where(function ($subQuery1) use ($args) {
                        $subQuery1->where('code', 'LIKE', "%{$args['employee_filter']}%")
                            ->orWhere('full_name', 'LIKE', "%{$args['employee_filter']}%");
                    });
                }

                if (!empty($args['department_filter'])) {
                    $subQuery->whereIn('client_department_id', $args['department_filter']);
                }
            });
        }

        return $query;
    }

    public function getWorktimeRegisterTimesheet($root, array $args)
    {
        $timesheet = Timesheet::select('*')->where('id', $args['timesheet_id'])->first();

        $checkIn = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in);
        $checkOut = Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_out);

        $query = WorktimeRegister::query()
            ->where('client_employee_id', $timesheet->client_employee_id)
            ->isApproved()
            ->whereType($args['type'])
            ->where(function ($subQuery) use ($checkIn, $checkOut) {
                $subQuery->whereBetween('start_time', [$checkIn, $checkOut])
                    ->orWhereBetween('end_time', [$checkIn, $checkOut])
                    ->orWhere(function ($query) use ($checkIn) {
                        $query->where('start_time', '<=', $checkIn)
                            ->where('end_time', '>=', $checkIn);
                    });
            });

        if (isset($args['sub_type']) && $args['sub_type']) {
            $query = $query->where('sub_type', $args['sub_type']);
        }

        $requests = $query->get();

        return $requests;
    }

    /**
     * @throws AuthenticationException
     * @throws HumanErrorException
     */
    public function validateLeaveAndBusiness($root, array $args)
    {
        $isNotValidated = [];
        $isCoincident = [];
        $newPeriods = $args['workTimeRegisterPeriod']['create'];
        $clientEmployeeId = $args['client_employee_id'];
        $employee = ClientEmployee::find($clientEmployeeId);
        $clientSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
        $dayBeginMark = $clientSetting ? $clientSetting->getTimesheetDayBeginAttribute() : '00:00';
        $client = Client::select('id', 'timesheet_min_time_block')->where('id', $employee->client_id)->first();
        $type = $args['type'];
        $subType = $args['sub_type'];

        if (count($newPeriods) == 0) return false;

        // Validate setting authorized leave_woman leave
        $category = $args['category'] ?? '';
        if ($category && $category == 'woman_leave' && empty($clientSetting->authorized_leave_woman_leave)) {
            throw new HumanErrorException(__("not_enable_setting_leave_woman_leave"));
        }

        // Validate form request when exceed the approve deadline of the past
        WorktimeRegisterHelper::checkValidateDeadlineApprove($newPeriods, $employee);

        // Validate not create form when user change setting
        WorktimeRegisterHelper::validateWhenUserChangeSetting($args, $clientSetting);

        // Validate not work schedule, off day and holiday
        {
            $inverseType = $type == Constant::TYPE_LEAVE ? Constant::TYPE_BUSINESS : Constant::TYPE_LEAVE;
            $fromDate = Carbon::parse($args['start_time'])->format('Y-m-d');
            $toDate = Carbon::parse($args['end_time'])->format('Y-m-d');
            $listDateErrorHoliday = [];
            $listDateErrorOffDay = [];
            $listDateNotWorkSchedule = [];
            foreach ($newPeriods as $newPeriod) {
                $previousDate = $newPeriod['type_register'] && $dayBeginMark > $newPeriod['start_time'];
                $dateSchedule = $previousDate ? Carbon::parse($newPeriod['date_time_register'])->subDay()->format('Y-m-d') : Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $dateFormat = $previousDate ? Carbon::parse($newPeriod['date_time_register'])->subDay()->format('d-m-Y') : Carbon::parse($newPeriod['date_time_register'])->format('d-m-Y');
                $workSchedule = $employee->getWorkSchedule($dateSchedule);
                if (!$workSchedule) {
                    $listDateNotWorkSchedule[] = $dateFormat;
                }

                if ($type == Constant::TYPE_LEAVE) {
                    if (!empty($workSchedule->is_off_day)) {
                        $listDateErrorOffDay[] = $dateFormat;
                    }
                    if (!empty($workSchedule->is_holiday)) {
                        $listDateErrorHoliday[] = $dateFormat;
                    }
                }
            }

            if (count($listDateNotWorkSchedule)) {
                $dates = implode(',', $listDateNotWorkSchedule);
                throw new HumanErrorException(__("warning.E9.validate") . '</br><div>' . $dates . '</div>');
            }

            if (count($listDateErrorOffDay)) {
                $dates = implode(',', $listDateErrorOffDay);
                throw new HumanErrorException(__("warning.E5.validate") . '</br><div>' . $dates . '</div>');
            }

            if (count($listDateErrorHoliday)) {
                $dates = implode(',', $listDateErrorHoliday);
                throw new HumanErrorException(__("warning.E6.validate") . '</br><div>' . $dates . '</div>');
            }
        }

        // Validate flexible
        {
            $typeException = $employee->timesheet_exception;
            $workTemplate = $employee->workScheduleGroupTemplate;
            $notEnoughBlock = [];
            foreach ($newPeriods as $newPeriod) {
                $startPeriod = $newPeriod['start_time'];
                $endPeriod = $newPeriod['end_time'];

                $datePeriod = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $startTime = Carbon::parse($datePeriod . ' ' . $newPeriod['start_time']);
                $endTime = !empty($newPeriod['next_day']) ? Carbon::parse($datePeriod . ' ' . $endPeriod)->addDay() : Carbon::parse($datePeriod . ' ' . $endPeriod);

                $previousDate = $newPeriod['type_register'] && $dayBeginMark > $startPeriod;
                $endDay = $previousDate ? Carbon::parse($datePeriod . ' ' . $dayBeginMark) : Carbon::parse($datePeriod . ' ' . $dayBeginMark)->addDay();
                if ($endDay->isBetween($startTime, $endTime, false)) {
                    throw new HumanErrorException(__("warning.E17.validate", ['dayBeginMark' => $dayBeginMark]));
                }

                // Check case flexible. User can adjust hours of work schedule
                $dateSchedule = $previousDate ? Carbon::parse($newPeriod['date_time_register'])->subDay()->format('Y-m-d') : Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $workSchedule = $employee->getWorkSchedule($dateSchedule);
                if (!empty($workSchedule->check_in) && !empty($workSchedule->check_out)) {
                    if (!$newPeriod['type_register']) continue;
                    if (!$endTime->isAfter($startTime)) {
                        if ($type == Constant::TYPE_BUSINESS && ($workSchedule->is_holiday || $workSchedule->is_off_day)) {
                            continue;
                        } else {
                            throw new HumanErrorException(__("validation.real_start_date"));
                        }
                    }
                    if ($typeException == Constant::TYPE_FLEXIBLE_TIMESHEET && !$workSchedule->shift_enabled && isset($newPeriod['change_flexible_checkin'])) {
                        $flexibleCheckIn = $newPeriod['change_flexible_checkin'];
                        // Calculation flexible checkout
                        $flexibleCheckout = WorkTimeRegisterPeriodHelper::calculationFlexibleCheckout($flexibleCheckIn, $workTemplate);
                        if (strtotime($flexibleCheckIn) <= strtotime($workTemplate->core_time_in)) {
                            if (strtotime($startPeriod) < strtotime($flexibleCheckIn)) {
                                throw new HumanErrorException(__("warning.E4.validate"));
                            }
                        } else {
                            throw new HumanErrorException(__("can_not_adjust_start_work_great_than_coretime"));
                        }
                        if (strtotime($endPeriod) > strtotime($flexibleCheckout)) {
                            throw new HumanErrorException(__("warning.E4.validate"));
                        }
                    } else {
                        if (!$clientSetting->enable_multiple_shift) {
                            $workCheckin = Carbon::parse($dateSchedule . ' ' . $workSchedule->check_in);
                            $workCheckout = Carbon::parse($dateSchedule . ' ' . $workSchedule->check_out);
                            if ($workSchedule->next_day) {
                                $workCheckout = $workCheckout->addDay();
                            }
                            if ($startTime->isBefore($workCheckin) || $endTime->isAfter($workCheckout)) {
                                throw new HumanErrorException(__("warning.E4.validate"));
                            }
                        }
                    }

                    $object = Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END);
                    if ($newPeriod['type_register'] && $client->timesheet_min_time_block) {
                        $requestMinutes = PeriodHelper::countMinutes($object);
                        if ($remainder = $requestMinutes % $client->timesheet_min_time_block) {
                            $notEnoughBlock[] = array_merge($newPeriod, [
                                'remainder' => $remainder
                            ]);
                        }
                    }
                }

                if ($workSchedule->shift_enabled) {
                    $dateList[] = $dateSchedule;
                }
            }

            if ($clientSetting->enable_multiple_shift && !empty($dateList)) {
                $hasFlexible = Timesheet::where('client_employee_id', $employee->id)
                    ->whereIn('log_date', $dateList)
                    ->whereHas('timesheetShiftMapping', function ($query) {
                        $query->whereNull('check_in');
                    })
                    ->whereHas('timesheetShiftMapping.timesheetShift', function ($query) {
                        $query->where('shift_type', TimesheetShift::FLEXIBLE_SHIFT);
                    })
                    ->count();

                if ($hasFlexible) {
                    throw new HumanErrorException(__("warning.E13.validate"));
                }
            }
        }

        // Validate not enough block
        if (count($notEnoughBlock)) {
            throw new HumanErrorException(__("warning.E7.validate", ['number' => $client->timesheet_min_time_block]));
        }

        // Validate inverse type
        {
            $periodsInverse = WorkTimeRegisterPeriodHelper::getPeriodByCondition($employee->id, [$inverseType], $fromDate, $toDate);
            $groupedPeriodsInverse = $periodsInverse->groupBy('worktime_register_id')->all();
            if ($newPeriods && $groupedPeriodsInverse) {
                foreach ($newPeriods as $newPeriod) {
                    // Check - WFH application & leave application have the same time period
                    $date = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                    $startTime = Carbon::parse($date . ' ' . $newPeriod['start_time']);
                    $endTime = !empty($newPeriod['next_day']) ? Carbon::parse($date . ' ' . $newPeriod['end_time'])->addDay() : Carbon::parse($date . ' ' . $newPeriod['end_time']);
                    $current = new PeriodCollection(Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END));

                    foreach ($groupedPeriodsInverse as $items) {
                        foreach ($items as $item) {
                            $collection = new PeriodCollection();
                            $st = '00:00:00';
                            $et = '23:59:59';
                            if ($item->type_register == 1) {
                                $st = $item->start_time;
                                $et = $item->end_time;
                            }

                            $s = Carbon::parse($item->date_time_register . ' ' . $st);
                            $e = $item->next_day ? Carbon::parse($item->date_time_register . ' ' . $et)->addDay() : Carbon::parse($item->date_time_register . ' ' . $et);

                            if ($e->isBefore($s)) {
                                continue;
                            }
                            $collection = $collection->add(Period::make($s, $e, Precision::MINUTE, Boundaries::EXCLUDE_END));
                            $overlaps = $current->overlap($collection);
                            if (count($overlaps)) {
                                $isCoincident[] = $item;
                            }
                        }
                    }
                }
            }
            if (count($isCoincident)) {
                $errorContent = [];
                foreach ($isCoincident as $error) {
                    $dateTimeRegister = Carbon::parse($error->date_time_register)->format('d-m-Y');
                    if ($error->type_register) {
                        $startTime = Carbon::parse('2021-01-01 ' . $error->start_time)->format('H:i');
                        $endTime = Carbon::parse('2021-01-01 ' . $error->end_time)->format('H:i');
                        $errorContent[] = $dateTimeRegister . ': ' . $startTime . ' - ' . $endTime;
                    } else {
                        $errorContent[] = $dateTimeRegister;
                    }
                }

                $errorContent = implode(', ', array_unique($errorContent));
                if ($inverseType === Constant::TYPE_LEAVE) {
                    throw new HumanErrorException(__("warning.E2.validate", ['datetime' => $errorContent]));
                } else {
                    throw new HumanErrorException(__("warning.E1.validate", ['datetime' => $errorContent]));
                }
            }
        }

        if ($type == Constant::TYPE_LEAVE && $newPeriods) {
            // Check leave request
            if ($subType == Constant::AUTHORIZED_LEAVE && $clientSetting->enable_paid_leave_rule && $category == 'year_leave') {
                LeaveHelper::checkYearLeaveBalances($clientSetting, $newPeriods, $employee);
            } elseif ($category !== 'year_leave' && !Str::isUuid($category)) {
                LeaveHelper::checkLeaveBalances($clientSetting, $newPeriods, $category, $subType, $employee);
            }
        }

        // Validate the same time of application
        {
            $newPeriodObjects = [];
            foreach ($newPeriods as $newPeriod) {
                $date = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $startTime = Carbon::parse($date . ' 00:00:00');
                $endTime = Carbon::parse($date . ' 23:59:59');
                if ($newPeriod['type_register'] == 1) {
                    $startTime = Carbon::parse($date . ' ' . $newPeriod['start_time']);
                    $endTime = !empty($newPeriod['next_day']) ? Carbon::parse($date . ' ' . $newPeriod['end_time'])->addDay() : Carbon::parse($date . ' ' . $newPeriod['end_time']);
                }

                try {
                    $newPeriodObjects[] = Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END);
                } catch (Exception $e) {
                    throw new HumanErrorException(__("error.invalid_time"));
                }
            }

            foreach ($newPeriodObjects as $index => $newPeriodObject) {
                $periodCollectionOverlaps = $newPeriodObject->overlap(...$newPeriodObjects);
                if (count($periodCollectionOverlaps) > 1) {
                    $isNotValidated[] = $newPeriods[$index];
                }
            }
            if (count($isNotValidated)) {
                throw new HumanErrorException(__("warning.E3.validate"));
            }
        }

        // Validate the other application
        {
            $periods = WorkTimeRegisterPeriodHelper::getPeriodByCondition($employee->id, [$type], $fromDate, $toDate);
            $groupedPeriods = !$periods->isEmpty() ? $periods->groupBy('worktime_register_id')->all() : [];
            foreach ($newPeriods as $newPeriod) {
                $date = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
                $startTime = Carbon::parse($date . ' 00:00:00');
                $endTime = Carbon::parse($date . ' 23:59:59');
                if ($newPeriod['type_register'] == 1) {
                    $startTime = Carbon::parse($date . ' ' . $newPeriod['start_time']);
                    $endTime = !empty($newPeriod['next_day']) ? Carbon::parse($date . ' ' . $newPeriod['end_time'])->addDay() : Carbon::parse($date . ' ' . $newPeriod['end_time']);
                }
                $current = new PeriodCollection(Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END));
                foreach ($groupedPeriods as $items) {
                    foreach ($items as $item) {

                        if (is_null($item->start_time) || is_null($item->end_time)) {
                            continue;
                        }

                        $collection = new PeriodCollection();
                        $st = '00:00:00';
                        $et = '23:59:59';

                        if ($item->type_register == 1) {
                            $st = $item->start_time;
                            $et = $item->end_time;
                        }

                        $s = Carbon::parse($item->date_time_register . ' ' . $st);
                        $e = Carbon::parse($item->date_time_register . ' ' . $et);
                        if ($s->equalTo($startTime) && $e->equalTo($endTime)) {
                            // Only for off day, holiday (00:00)
                            $isNotValidated[] = $item;
                            continue;
                        }

                        if ($item->next_day) {
                            $e = $e->addDay();
                        }

                        if ($e->isBefore($s)) {
                            continue;
                        }

                        $collection = $collection->add(Period::make($s, $e, Precision::MINUTE, Boundaries::EXCLUDE_END));
                        $overlaps = $current->overlap($collection);
                        if (count($overlaps)) {
                            $isNotValidated[] = $item;
                        }
                    }
                }
            }

            if (count($isNotValidated)) {
                $errorContent = [];
                foreach ($isNotValidated as $error) {
                    $dateTimeRegister = Carbon::parse($error->date_time_register)->format('d-m-Y');
                    if ($error->type_register) {
                        $startTime = Carbon::parse('2021-01-01 ' . $error->start_time)->format('H:i');
                        $endTime = Carbon::parse('2021-01-01 ' . $error->end_time)->format('H:i');
                        $errorContent[] = $dateTimeRegister . ': ' . $startTime . ' - ' . $endTime;
                    } else {
                        $errorContent[] = $dateTimeRegister;
                    }
                }

                $errorContent = implode(', ', array_unique($errorContent));
                if ($type == Constant::TYPE_BUSINESS) {
                    throw new HumanErrorException(__("warning.E1.validate", ['datetime' => $errorContent]));
                } else {
                    throw new HumanErrorException(__("warning.E2.validate", ['datetime' => $errorContent]));
                }
            }
        }

        return true;
    }

    /**
     * @throws AuthenticationException
     * @throws HumanErrorException
     */
    public function validateOvertime($root, array $args)
    {
        $clientEmployeeId = $args['client_employee_id'];
        $notEnoughBlock = [];
        $newPeriods = $args['workTimeRegisterPeriod']['create'];
        $fromDate = $args['start_time'];
        $toDate = $args['end_time'];
        $types = [Constant::TYPE_LEAVE, Constant::TYPE_BUSINESS];
        $employee = ClientEmployee::find($clientEmployeeId);
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
        $dayBeginMark = $clientWorkFlowSetting ? $clientWorkFlowSetting->getTimesheetDayBeginAttribute() : '00:00';
        $workScheduleGroupTemplate = $employee->workScheduleGroupTemplate;
        $client = Client::select('id', 'ot_min_time_block')->where('id', $employee->client_id)->first();

        // Validate form request when exceed the approve deadline of the past
        WorktimeRegisterHelper::checkValidateDeadlineApprove($newPeriods, $employee);

        // Validate makeup request form
        if ($args['type'] === Constant::TYPE_MAKEUP && (empty($clientWorkFlowSetting->enable_makeup_request_form) || (empty($workScheduleGroupTemplate->enable_makeup_or_ot_form)))) {
            throw new HumanErrorException(__("not_create_makeup_request") . ' ');
        }
        if ($args['type'] === Constant::TYPE_MAKEUP || $args['type'] === Constant::TYPE_OT) {
            $types = array_merge($types, [Constant::TYPE_OT, Constant::TYPE_MAKEUP]);
        } else {
            $types[] = $args['type'];
        }
        $startDate = Carbon::parse($fromDate)->format('Y-m-d');
        $endDate = Carbon::parse($toDate)->format('Y-m-d');

        $periods = WorkTimeRegisterPeriodHelper::getPeriodByCondition($employee->id, $types, $startDate, $endDate);
        foreach ($newPeriods as $key => $newPeriod) {
            $datePeriod = Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');
            $startTime = Carbon::parse($datePeriod . ' ' . $newPeriod['start_time']);
            $endTime = !empty($newPeriod['next_day']) ? Carbon::parse($datePeriod . ' ' . $newPeriod['end_time'])->addDay() : Carbon::parse($datePeriod . ' ' . $newPeriod['end_time']);

            if ($startTime->format('H:i') == $endTime->format('H:i') || $startTime->gte($endTime)) {
                // $isNotValidated[] = $newPeriod;
                $errorContent = '<div> ' . $startTime->format('d-m-Y H:i') . ' - ' . $endTime->format('d-m-Y H:i') . '</div>';
                throw new HumanErrorException(__("error.invalid_time") . $errorContent);
            }

            $previousDate = $newPeriod['type_register'] && $dayBeginMark > $newPeriod['start_time'];
            $endDay = $previousDate ? Carbon::parse($datePeriod . ' ' . $dayBeginMark) : Carbon::parse($datePeriod . ' ' . $dayBeginMark)->addDay();

            if ($endDay->isBetween($startTime, $endTime, false)) {
                throw new HumanErrorException(__("warning.E17.validate", ['dayBeginMark' => $dayBeginMark]));
            }

            $dateSchedule = $previousDate ? Carbon::parse($newPeriod['date_time_register'])->subDay()->format('Y-m-d') : Carbon::parse($newPeriod['date_time_register'])->format('Y-m-d');

            $workSchedule = $employee->getWorkSchedule($dateSchedule);

            if (!$workSchedule) {
                throw new HumanErrorException(__("warning.E9.validate"));
            }

            $current = new PeriodCollection(Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END));

            // Check overlap with work_schedule
            $ts = Timesheet::where('client_employee_id', $employee->id)->whereDate('log_date', $dateSchedule)->first();
            if ($ts && $ts->isUsingMultiShift($clientWorkFlowSetting)) {
                $shiftMapping = $ts->timesheetShiftMapping;
                $schedulePeriods = new PeriodCollection();
                foreach ($shiftMapping as $item) {
                    if ($item->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT && !$item->check_in) {
                        throw new HumanErrorException(__("warning.E13.validate"));
                    }

                    $item->precision_type = Precision::MINUTE;
                    $item->boundaries_type = Boundaries::EXCLUDE_END;
                    $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                    $schedulePeriods = PeriodHelper::merge2Collections($schedulePeriods, $schedulePeriodsWithoutRest);
                }
                $overlap = $current->overlap($schedulePeriods);
                if (!$overlap->isEmpty()) {
                    throw new HumanErrorException(__("warning.E10.validate"));
                }
            } else {
                // Check overlap with work_schedule
                if (!$workSchedule->is_holiday && !$workSchedule->is_off_day) {
                    // Validate flex
                    if ($employee->timesheet_exception == Constant::TYPE_FLEXIBLE_TIMESHEET && !$workSchedule->shift_enabled && isset($newPeriod['change_flexible_checkin'])) {
                        $workSchedule->check_in = $newPeriod['change_flexible_checkin'];
                        // Calculation flexible checkout
                        $workSchedule->check_out = WorkTimeRegisterPeriodHelper::calculationFlexibleCheckout($workSchedule->check_in, $workScheduleGroupTemplate);
                    }

                    $timeCheckIn = Carbon::parse($dateSchedule . ' ' . $workSchedule->check_in . ':00');
                    $timeCheckout = Carbon::parse($dateSchedule . ' ' . $workSchedule->check_out . ':00');
                    if (!empty($workSchedule->next_day)) {
                        $timeCheckout->addDay();
                    }

                    $collection = new PeriodCollection();
                    if (!empty($workSchedule->start_break) && !empty($workSchedule->end_break)) {
                        $timeStartBreak = Carbon::parse($dateSchedule . ' ' . $workSchedule->start_break . ':00');
                        if ($workSchedule->start_break < $workSchedule->check_in) {
                            $timeStartBreak->addDay();
                        }
                        $timeEndBreak = Carbon::parse($dateSchedule . ' ' . $workSchedule->end_break . ':00');
                        if ($workSchedule->end_break < $workSchedule->check_in) {
                            $timeEndBreak->addDay();
                        }

                        // Check time checkout > time end break then add time for check overlap
                        if ($timeCheckout->isAfter($timeEndBreak)) {
                            if ($timeCheckout->isAfter($timeCheckIn)) {
                                if ($timeStartBreak->equalTo($timeEndBreak)) {
                                    $collection = $collection->add(Period::make($timeCheckIn, $timeCheckout, Precision::MINUTE, Boundaries::EXCLUDE_END));
                                } else {
                                    $collection = $collection->add(Period::make($timeCheckIn, $timeStartBreak, Precision::MINUTE, Boundaries::EXCLUDE_END));
                                    $collection = $collection->add(Period::make($timeEndBreak, $timeCheckout, Precision::MINUTE, Boundaries::EXCLUDE_END));
                                }
                            }
                        } else {
                            // if time checkout < time end break then time break outside worktime
                            $collection = $collection->add(Period::make($timeCheckIn, $timeCheckout, Precision::MINUTE, Boundaries::EXCLUDE_END));
                        }
                    } else {
                        $collection = $collection->add(Period::make($timeCheckIn, $timeCheckout, Precision::MINUTE, Boundaries::EXCLUDE_END));
                    }


                    $overlaps = $current->overlap($collection);
                    if (count($overlaps)) {
                        throw new HumanErrorException(__("warning.E10.validate"));
                    }
                }
            }

            if ($newPeriod['type_register'] && $client->ot_min_time_block) {
                $requestMinutes = PeriodHelper::countMinutes(Period::make($startTime, $endTime, Precision::MINUTE, Boundaries::EXCLUDE_END));
                if ($remainder = $requestMinutes % $client->ot_min_time_block) {
                    $notEnoughBlock[] = array_merge($newPeriod, [
                        'remainder' => $remainder
                    ]);
                }
            }


            // Throw error ot_min_time_block
            if (count($notEnoughBlock)) {
                throw new HumanErrorException(__("warning.E7.validate", ['number' => $client->ot_min_time_block]));
            }

            $collection = new PeriodCollection();
            for ($i = $key + 1; $i < count($newPeriods); $i++) {
                $dateTemp = Carbon::parse($newPeriods[$i]['date_time_register'])->format('Y-m-d');
                if ($dateTemp === $datePeriod) {
                    $addDay = $newPeriods[$i]['next_day'] ? 1 : 0;
                    $st = Carbon::parse($datePeriod . ' ' . $newPeriods[$i]['start_time']);
                    $et = Carbon::parse($datePeriod . ' ' . $newPeriods[$i]['end_time'])->addDay($addDay);
                    $collection = $collection->add(Period::make($st, $et, Precision::MINUTE, Boundaries::EXCLUDE_END));
                }
            }
            $overlaps = $current->overlap($collection);
            if (count($overlaps)) {
                throw new HumanErrorException(__("warning.E3.validate"));
            }

            if ($periods) {
                $groupedPeriods = $periods->groupBy('worktime_register_id')->all();

                // Check overlap registered periods
                foreach ($groupedPeriods as $items) {
                    foreach ($items as $item) {
                        $collection = new PeriodCollection();
                        $st = $item->start_time;
                        $et = $item->end_time;

                        $addDay = $item['next_day'] ? 1 : 0;
                        $s = Carbon::parse($item->date_time_register . ' ' . $st);
                        $e = Carbon::parse($item->date_time_register . ' ' . $et)->addDay($addDay);
                        $collection = $collection->add(Period::make($s, $e, Precision::MINUTE, Boundaries::EXCLUDE_END));
                        $overlaps = $current->overlap($collection);
                        if ($args['type'] == Constant::TYPE_OT && $clientWorkFlowSetting->enable_makeup_request_form && count($overlaps) > 0) {
                            $wrt = $item->worktimeRegister;
                            $wrt->realStatus = true;
                            $params = [
                                'id' => $wrt->id,
                                'message' => 'the_same_auto_compensatory_form',
                                'status' => Constant::APPROVE_STATUS
                            ];
                            // Auto form
                            if ($wrt->type == Constant::TYPE_MAKEUP && $wrt->status == Constant::APPROVE_STATUS && is_null($wrt->approved_by)) {
                                $params['is_auto'] = true;
                                throw new HumanErrorException(json_encode($params));
                            } elseif ($wrt->type == Constant::TYPE_MAKEUP) {
                                $params['is_auto'] = false;
                                $params['status'] = $wrt->status;
                                $cancelApproves = $wrt->approves->where('type', 'CLIENT_REQUEST_CANCEL_OT')->whereNull('processing_state')->first();
                                if ($wrt->status == Constant::APPROVE_STATUS) {
                                    if ($cancelApproves) {
                                        $params['status'] = 'cancel_approved';
                                        $params['message'] = "waring.application_is_waiting_by_management";
                                    } else {
                                        $params['message'] = "waring.cancel_compensatory_when_create_OT_form";
                                    }
                                } else {
                                    $params['message'] = "waring.cancel_compensatory_when_create_OT_form";
                                }

                                throw new HumanErrorException(json_encode($params));
                            }
                        }

                        if (count($overlaps) > 0) {
                            foreach ($overlaps as $period) {
                                $periodStart = new Carbon($period->getStart());
                                $periodEnd = new Carbon($period->getEnd());
                                $periodMinutes = $periodStart->diffInMinutes($periodEnd);
                                if ($periodMinutes > 0) {
                                    $wrt = $item->worktimeRegister;
                                    $dateTimeRegister = Carbon::parse($item->date_time_register)->format('d-m-Y');
                                    $startTime = Carbon::parse('2021-01-01 ' . $item->start_time)->format('H:i');
                                    $endTime = Carbon::parse('2021-01-01 ' . $item->end_time)->format('H:i');
                                    $errorContent = '<span style="font-weight: bold; color: red"> ' . $dateTimeRegister . ': ' . $startTime . ' - ' . $endTime . '</span>';
                                    switch ($wrt->type) {
                                        case Constant::TYPE_BUSINESS:
                                            throw new HumanErrorException(__("warning.E1.validate", ['datetime' => $errorContent]));
                                        case Constant::TYPE_LEAVE:
                                            throw new HumanErrorException(__("warning.E2.validate", ['datetime' => $errorContent]));
                                        case Constant::TYPE_MAKEUP:
                                            throw new HumanErrorException(__("warning.E12.validate", ['datetime' => $errorContent]));
                                        case Constant::TYPE_OT:
                                            throw new HumanErrorException(__("warning.E11.validate", ['datetime' => $errorContent]));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    public function exportExcel($root, array $args)
    {
        $fromDate = $args['from_date'] ?? null;
        $toDate = $args['to_date'] ?? null;
        $status = $args['status'] ?? [];
        $statusNotContainWaitApproved = array_diff($status, [Constant::WAIT_CANCEL_APPROVE]);
        $type = $args['type'];
        $groupIds = !empty($args['group_ids']) ? $args['group_ids'] : [];
        $employeeFilter = !empty($args['employee_filter']) ? $args['employee_filter'] : '';
        $departmentFilter = !empty($args['department_filter']) ? $args['department_filter'] : [];
        $client = auth()->user()->client;
        $clientSetting = ClientWorkflowSetting::where('client_id', $client->id)->first();
        $standardWorkHours = $client->standard_work_hours_per_day;
        $types = [];

        // Set type all for OT and compensatory
        if ($type == Constant::TYPE_OT_ALL) {
            $types = Constant::OT_TYPES;
        } else {
            $types[] = $type;
        }

        $data = ClientEmployee::where('client_id', auth()->user()->client_id)
            ->with(
                [
                    "worktimeRegister" => function ($q) use ($args, $fromDate, $toDate, $status, $types, $type, $statusNotContainWaitApproved) {
                        if (!empty($types)) {
                            $q->whereIn('type', $types);
                        }

                        if (!empty($args['sub_type'])) {
                            $q->where('sub_type', $args['sub_type']);
                        }

                        if (!empty($args['category'])) {
                            $q->where('category', $args['category']);
                        }

                        // Filter is assigned or not assigned application
                        if (in_array($type, Constant::OT_TYPES)) {
                            if (!empty($args['is_assigned'])) {
                                $q->whereNotNull('creator_id');
                            } else {
                                $q->whereNull('creator_id');
                            }
                        }

                        if (isset($fromDate) && isset($toDate)) {
                            $q->where(function ($query) use ($fromDate, $toDate) {
                                $query->where([
                                    ['work_time_registers.start_time', '>=', $fromDate],
                                    ['work_time_registers.start_time', '<=', $toDate]
                                ])->orWhere([
                                    ['work_time_registers.end_time', '>=', $fromDate],
                                    ['work_time_registers.end_time', '<=', $toDate]
                                ])->orWhere([
                                    ['work_time_registers.start_time', '>=', $fromDate],
                                    ['work_time_registers.end_time', '<=', $toDate]
                                ])->orWhere([
                                    ['work_time_registers.start_time', '<=', $fromDate],
                                    ['work_time_registers.end_time', '>=', $toDate]
                                ]);
                            });
                        }

                        $q->orderBy('work_time_registers.created_at');

                        $periodsQuery = function ($query) use ($fromDate, $toDate, $status, $statusNotContainWaitApproved) {
                            if (isset($fromDate) && isset($toDate)) {
                                $query->where('date_time_register', '>=', $fromDate)
                                    ->where('date_time_register', '<=', $toDate);
                            }
                            if (!empty($status)) {
                                $query->where(function ($subQuery) use ($status, $statusNotContainWaitApproved) {
                                    if (in_array(Constant::WAIT_CANCEL_APPROVE, $status)) {
                                        $subQuery->where('is_cancellation_approval_pending', 1);
                                    }

                                    if (!empty($statusNotContainWaitApproved)) {
                                        $subQuery->orWhere('is_cancellation_approval_pending', 0);
                                    }
                                });
                            }
                        };

                        $approvesQuery = function ($query) {
                            $query->whereRaw('NOT (approved_at IS NULL AND declined_at IS NULL)');
                        };

                        if (!empty($status)) {
                            $q->where(function ($query) use ($status, $statusNotContainWaitApproved, $fromDate, $toDate) {
                                if (in_array(Constant::WAIT_CANCEL_APPROVE, $status)) {
                                    $query->where(function ($subQuery) use ($status, $fromDate, $toDate) {
                                        $subQuery->whereHas('periods', function ($subQuery1) use ($status, $fromDate, $toDate) {
                                            if (isset($fromDate) && isset($toDate)) {
                                                $subQuery1->where('date_time_register', '>=', $fromDate)
                                                    ->where('date_time_register', '<=', $toDate);
                                            }
                                            $subQuery1->where('is_cancellation_approval_pending', 1);
                                        });
                                    });
                                }

                                if (!empty($statusNotContainWaitApproved)) {
                                    $query->orWhere(function ($subQuery) use ($statusNotContainWaitApproved, $fromDate, $toDate) {
                                        $subQuery->whereIn('work_time_registers.status', $statusNotContainWaitApproved)
                                            ->whereDoesntHave('periods', function ($query) use ($fromDate, $toDate) {
                                                if (isset($fromDate) && isset($toDate)) {
                                                    $query->where('date_time_register', '>=', $fromDate)
                                                        ->where('date_time_register', '<=', $toDate);
                                                }
                                                $query->where('is_cancellation_approval_pending', 1);
                                            });
                                    });
                                }
                            });
                        }

                        $q->with('periods', $periodsQuery);
                        $q->with('approves', $approvesQuery);
                    }
                ]
            )
            ->withCount([
                'worktimeRegisterPeriod' => function ($q) use ($args, $fromDate, $toDate, $status, $types, $type, $statusNotContainWaitApproved) {
                    if (!empty($types)) {
                        $q->whereIn('type', $types);
                    }
                    if (!empty($args['sub_type'])) {
                        $q->where('sub_type', $args['sub_type']);
                    }

                    if (!empty($args['category'])) {
                        $q->where('category', $args['category']);
                    }

                    // Filter is assigned or not assigned application
                    if (in_array($type, Constant::OT_TYPES)) {
                        if (!empty($args['is_assigned'])) {
                            $q->whereNotNull('creator_id');
                        } else {
                            $q->whereNull('creator_id');
                        }
                    }

                    if (!empty($status)) {
                        $q->where(function ($query) use ($status, $fromDate, $toDate, $statusNotContainWaitApproved) {
                            if (in_array(Constant::WAIT_CANCEL_APPROVE, $status)) {
                                logger("5");
                                $query->where('is_cancellation_approval_pending', 1);
                            }

                            if (!empty($statusNotContainWaitApproved)) {
                                $query->orWhere(function ($subQuery) use ($statusNotContainWaitApproved, $fromDate, $toDate) {
                                    $subQuery->whereHas('worktimeRegister', function ($subQuery1) use ($statusNotContainWaitApproved, $fromDate, $toDate) {
                                        $subQuery1->whereIn('status', $statusNotContainWaitApproved);
                                        $subQuery1->whereDoesntHave('worktimeRegisterPeriod', function ($subQuery2) use ($fromDate, $toDate) {
                                            if (isset($fromDate) && isset($toDate)) {
                                                $subQuery2->where('date_time_register', '>=', $fromDate)
                                                    ->where('date_time_register', '<=', $toDate);
                                            }
                                            $subQuery2->where('is_cancellation_approval_pending', 1);
                                        });
                                    });
                                });
                            }
                        });
                    }

                    if (isset($fromDate) && isset($toDate)) {
                        $q->where('date_time_register', '>=', $fromDate)
                            ->where('date_time_register', '<=', $toDate);
                    }
                },
            ])
            ->withCount([
                'worktimeRegister' => function ($q) use ($args, $fromDate, $toDate, $status, $types, $type, $statusNotContainWaitApproved) {
                    if (!empty($types)) {
                        $q->whereIn('type', $types);
                    }
                    if (!empty($args['sub_type'])) {
                        $q->where('sub_type', $args['sub_type']);
                    }

                    if (!empty($args['category'])) {
                        $q->where('category', $args['category']);
                    }

                    // Filter is assigned or not assigned application
                    if (in_array($type, Constant::OT_TYPES)) {
                        if (!empty($args['is_assigned'])) {
                            $q->whereNotNull('creator_id');
                        } else {
                            $q->whereNull('creator_id');
                        }
                    }

                    if (!empty($status)) {
                        $q->where(function ($query) use ($status, $statusNotContainWaitApproved) {
                            if (in_array(Constant::WAIT_CANCEL_APPROVE, $status)) {
                                $query->where(function ($subQuery) use ($status) {
                                    $subQuery->whereHas('periods', function ($subQuery1) use ($status) {
                                        $subQuery1->where('is_cancellation_approval_pending', 1);
                                    });
                                });
                            }

                            if (!empty($statusNotContainWaitApproved)) {
                                $query->orWhere(function ($subQuery) use ($statusNotContainWaitApproved) {
                                    $subQuery->whereIn('work_time_registers.status', $statusNotContainWaitApproved)
                                        ->whereHas('periods', function ($subQuery1) {
                                            $subQuery1->where('is_cancellation_approval_pending', 0);
                                        });
                                });
                            }
                        });
                    }

                    if (isset($fromDate) && isset($toDate)) {
                        $q->where(function ($query) use ($fromDate, $toDate) {
                            $query->where([
                                ['work_time_registers.start_time', '>=', $fromDate],
                                ['work_time_registers.start_time', '<=', $toDate]
                            ])->orWhere([
                                ['work_time_registers.end_time', '>=', $fromDate],
                                ['work_time_registers.end_time', '<=', $toDate]
                            ])->orWhere([
                                ['work_time_registers.start_time', '>=', $fromDate],
                                ['work_time_registers.end_time', '<=', $toDate]
                            ])->orWhere([
                                ['work_time_registers.start_time', '<=', $fromDate],
                                ['work_time_registers.end_time', '>=', $toDate]
                            ]);
                        });
                    }
                },
            ]);

        if (!empty($groupIds)) {
            $user = Auth::user();
            $listClientEmployeeId = $user->getListClientEmployeeByGroupIds($user, $groupIds);
            $data->whereIn('id', $listClientEmployeeId);
        }

        if (!empty($departmentFilter)) {
            $data->whereIn('client_department_id', $departmentFilter);
        }

        if (!empty($employeeFilter)) {
            $data->where(function ($query) use ($employeeFilter) {
                $query->where('code', 'LIKE', "%{$employeeFilter}%")
                    ->orWhere('full_name', 'LIKE', "%{$employeeFilter}%");
            });
        }

        $data = $data->having('worktime_register_period_count', '>', 0)
            ->having('worktime_register_count', '>', 0)
            ->orderBy('client_employees.full_name')->get();
        $user = auth()->user();
        $timezone_name = !empty($user->timezone_name) ? $user->timezone_name : Constant::TIMESHEET_TIMEZONE;
        // Check type OT by compare Request OT with Work Schedule
        if ($type == Constant::TYPE_OT_ALL || $type == Constant::TYPE_OT || $type == Constant::MAKEUP_TYPE) {
            foreach ($data as &$item) {
                $worktimeRegisters = $item->worktimeRegister;
                foreach ($worktimeRegisters as &$worktimeRegister) {
                    $periods = $worktimeRegister->periods;
                    foreach ($periods as &$period) {
                        $dateOT = Carbon::parse($period->date_time_register)->format('Y-m-d');
                        $dateSchedule = $item->getWorkSchedule($dateOT);
                        if ($worktimeRegister->type == Constant::TYPE_OT) {
                            if (!empty($dateSchedule->is_off_day)) {
                                $period->sub_type = !is_null($worktimeRegister->creator_id) ? 'assigned_ot_weekend' : 'ot_weekend';
                            } else if (!empty($dateSchedule->is_holiday)) {
                                $period->sub_type = !is_null($worktimeRegister->creator_id) ? 'assigned_ot_holiday' : 'ot_holiday';
                            } else {
                                $period->sub_type = !is_null($worktimeRegister->creator_id) ? 'assigned_ot_weekday' : 'ot_weekday';
                            }
                        } else {
                            $period->sub_type = !is_null($worktimeRegister->creator_id) ? 'assigned_ot_makeup' : 'ot_makeup';
                        }
                    }
                    $worktimeRegister->periods = $periods;
                }
                $item->worktimeRegister = $worktimeRegisters;
            }
        }
        // add status "approved" for export
        if(count($status) == 1 && $status[0] == Constant::APPROVE_STATUS){
            $status = Constant::APPROVE_STATUS;
        }

        $params = [
            'data' => $data,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'type' => $type,
            'status' => $status,
            'timezone_name' => $timezone_name,
            'standard_work_hours' => $standardWorkHours
        ];

        if ($type == Constant::TYPE_LEAVE) {
            $params['wt_category'] = WorktimeRegisterCategory::select('id', 'category_name')->where('client_id', auth()->user()->client_id)->get();
        }

        // Export excel
        $extension = '.xlsx';

        // Set name file
        $nameFileType = $args['type'];
        if ($args['type'] == Constant::TYPE_OT_ALL) {
            if (empty($clientSetting->enable_makeup_request_form)) {
                $nameFileType = Constant::TYPE_OT;
            } else {
                $nameFileType = 'overtime_and_compensatory_request';
            }
        } else if ($args['type'] == Constant::TYPE_MAKEUP) {
            $nameFileType = 'compensatory_request';
        } else if ($args['type'] == Constant::TYPE_BUSINESS) {
            $nameFileType = 'business_request';
        }
        $fileName = Str::upper($nameFileType) . "_" . time() . $extension;

        // Save file
        $pathFile = 'WorktimeRegisterExport/' . $fileName;
        Excel::store((new WorktimeRegisterExport($params)), $pathFile, 'minio');

        $response = [
            'name' => $fileName,
            'url' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
        ];

        return json_encode($response);
    }

    public function requestCancel($root, array $args)
    {
        $wtr = WorktimeRegister::find($args['worktime_register_id']);
        $employee = $wtr->clientEmployee;
        if ($employee) {
            $clientSetting = ClientWorkflowSetting::where('client_id', $employee->client_id)->first();
            if ($clientSetting) {
                WorktimeRegisterHelper::validateWhenUserChangeSetting($wtr, $clientSetting);
            }
        }
        if ($wtr->periods->count() > 0) {
            if ($wtr->periods->count() == 1) {
                if (!$args['delete']) {
                    $wtr->update(['status' => 'canceled_approved']);
                    $wtr->periods->each(function ($period) {
                        $period->update(['status' => 'canceled_approved']);
                    });
                } else {
                    $wtr->delete();
                }
            } else {
                $start_time = $end_time = null;
                foreach ($wtr->periods as $item) {
                    if (
                        ($item->date_time_register == $args['date_time_register']) &&
                        (substr($item->start_time, 0, 5) == substr($args['start_time'], 0, 5)) &&
                        (substr($item->end_time, 0, 5) == substr($args['end_time'], 0, 5))
                    ) {
                        $item->delete();
                    } else {
                        $st = '00:00:00';
                        $et = '23:59:59';
                        if ($item->type_register == 1) {
                            $st = $item->start_time;
                            $et = $item->end_time;
                        }
                        if (!$start_time) {
                            $start_time = $item->date_time_register . ' ' . $st;
                        }
                        if (!$end_time) {
                            $end_time = $item->date_time_register . ' ' . $et;
                        }
                        $startTime = $item->date_time_register . ' ' . $st;
                        $endTime = ($item->next_day ? Carbon::parse($item->date_time_register . ' ' . $et)->addDay()->format('Y-m-d') : $item->date_time_register) . ' ' . $et;

                        if (!Carbon::parse($start_time)->isBefore(Carbon::parse($startTime))) {
                            $start_time = $startTime;
                        }
                        if (!Carbon::parse($end_time)->isAfter(Carbon::parse($endTime))) {
                            $end_time = $endTime;
                        }
                    }
                }
                $wtr->update([
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                ]);
                $approve = Approve::query()->where('target_id', $args['worktime_register_id'])->whereNotIn('type', Constant::TYPE_CANCEL_ADVANCED_APPROVE)->first();
                $workTimeRegisterPeriod = [];
                if ($approve !== NULL) {
                    $content = json_decode($approve->content, true);
                    foreach ($content['workTimeRegisterPeriod'] as $value) {
                        if ($value['date_time_register'] != $args['date_time_register']) {
                            array_push($workTimeRegisterPeriod, $value);
                        }
                    }
                    $content['workTimeRegisterPeriod'] = $workTimeRegisterPeriod;
                    $approve->content = json_encode($content);
                    $approve->save();
                }
            }
        }
    }

    public function getWorktimeRegisterPaidLeaveChange($root, array $args)
    {
        if (isset($args['sub_type']) && isset($args['category'])) {
            return WorktimeRegisterHelper::getLeaveChangeSummary($args['client_employee_id'], $args['sub_type'], $args['category']);
        } else {
            return WorktimeRegisterHelper::getLeaveChangeSummary($args['client_employee_id']);
        }
    }

    public function getYearPaidLeaveChange($root, array $args)
    {
        return WorktimeRegisterHelper::getYearPaidLeaveChange($args['client_employee_id']);
    }

    /** @noinspection SuspiciousBinaryOperationInspection */
    public function clientEmployeeCongTacRequestListType($root, array $args)
    {
        $arrTypeRegister = [];

        $typeRegister = Constant::TYPE_BUSSINESS;
        if (!empty($typeRegister)) {

            $user = Auth::user();
            $clientSetting = ClientWorkflowSetting::where('client_id', $user->client_id)->first();

            foreach ($typeRegister as $key => $item) {
                if(
                    ($key === 'business_trip_road' && (
                        !$clientSetting->enable_transportation_request ||
                        !$clientSetting->enable_bussiness_request_trip
                    )) ||
                    ($key === 'business_trip_airline' && (
                        !$clientSetting->enable_transportation_request ||
                        !$clientSetting->enable_bussiness_request_trip
                    )) ||
                    ($key === 'outside_working' && !$clientSetting->enable_bussiness_request_outside_working) ||
                    ($key === 'wfh' && !$clientSetting->enable_bussiness_request_wfh) ||
                    ($key === 'other' && !$clientSetting->enable_bussiness_request_other) ||
                    ($key === 'business_trip' && (
                        $clientSetting->enable_transportation_request ||
                        !$clientSetting->enable_bussiness_request_trip
                    ))
                ){
                    continue;
                }

                $arrTypeRegister[] = [
                    "label" => __($item['label']),
                    "value" => $item['value']
                ];
            }
        }

        return $arrTypeRegister;
    }

    public function deleteWorkTimeRegisterWithNotApprove($root, array $args)
    {
        $workTimeRegister = WorktimeRegister::find($args['id']);

        if ($workTimeRegister->type = Constant::TYPE_MAKEUP) {
            // Delete WorkTimeRegisterTimesheet
            $workTimeRegister->reCalculatedOTWhenCancel();
        }

        $this->deleteApprove('App\Models\WorktimeRegister', $args['id']);

        $workTimeRegister->workTimeRegisterPeriod()->delete();

        WorktimeRegister::withoutEvents(function () use($workTimeRegister) {
            $workTimeRegister->delete();
        });


        return true;
    }
}
