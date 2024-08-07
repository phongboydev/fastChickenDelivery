<?php

namespace App\Models;

use App\Jobs\AutoGenerateOTRequest;
use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;
use Znck\Eloquent\Traits\BelongsToThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Support\HanetHelper;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $log_date
 * @property string $activity
 * @property string $work_place
 * @property float $shift
 * @property int $working_hours
 * @property int $rest_hours
 * @property int $overtime_hours
 * @property string $check_in
 * @property string $start_next_day
 * @property string $check_out
 * @property string $next_day
 * @property string $leave_type
 * @property string $attentdant_status
 * @property string $work_status
 * @property string $paid_leave_hours
 * @property string unpaid_leave_hours
 * @property string $mission_hours
 * @property string $wfh_hours
 * @property string $outside_working_hours
 * @property string $other_business_hours
 * @property string $note
 * @property string $created_at
 * @property string $updated_at
 * @property string $work_schedule_group_template_id
 * @property string $mission_road_hours
 * @property string $mission_airline_hours
 * @property string $manual_makeup_hours
 */
class Timesheet extends Model
{

    use Concerns\UsesUuid, LogsActivity, HasAssignment, BelongsToThrough, HasFactory;

    public const STATUS_NGHI_PHEP_KHL = 'Nghỉ phép KHL';
    public const STATUS_NGHI_PHEP_HL = 'Nghỉ phép HL';
    public const STATUS_NGHI_CUOI_TUAN = 'Nghỉ cuối tuần';
    public const STATUS_NGHI_LE = 'Nghỉ lễ';
    public const STATUS_DI_LAM = 'Đi làm';

    protected static array $logAttributes = [
        'check_in', 'check_out', 'work_status', 'note', 'working_hours', 'rest_hours', 'overtime_hours', 'work_schedule_group_template_id', 'flexible_check_out', 'flexible_check_in', 'skip_hanet', 'skip_plan_flexible', 'makeup_hours', 'missing_hours_in_core_time', 'mission_road_hours', 'mission_airline_hours', 'manual_makeup_hours'
    ];

    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'timesheets';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    /**
     * @var array
     */
    protected $fillable = [
        'client_employee_id',
        'log_date',
        'activity',
        'work_place',
        'shift',
        'working_hours',
        'rest_hours',
        'overtime_hours',
        'midnight_overtime_hours',
        'check_in',
        'start_next_day',
        'check_out',
        'next_day',
        'leave_type',
        'attentdant_status',
        'work_status',
        'note',
        'created_at',
        'updated_at',
        'state',
        'reason',
        'work_schedule_group_template_id',
        'shift_enabled',
        'shift_is_off_day',
        'shift_is_holiday',
        'timesheet_shift_id',
        'next_day_break',
        'flexible_check_out',
        'flexible_check_in',
        'skip_hanet',
        'skip_plan_flexible'
    ];

    protected $workSchedule;
    protected $workScheduleGroupTemplate;
    protected $timesheetMinTimeBlock;
    protected $restHours;
    protected $isHoliday = null;
    public $dayBeginMark = "00:00";

    protected $isUpdateWorkSchedule = false;

    protected $periodMissingCoretime = [
        'check_in' => [],
        'check_out' => []
    ];

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'client_employee_id');
    }

    public function timesheetShiftMapping()
    {
        return $this->hasMany(TimesheetShiftMapping::class, 'timesheet_id')
            ->withAggregate('timesheetShift', 'check_in')
            ->orderBy('timesheet_shift_check_in', 'ASC');
    }

    public function workTimeRegisterTimesheets()
    {
        return $this->hasMany(WorkTimeRegisterTimesheet::class, 'timesheet_id');
    }

    public function workTimeRegister()
    {
        $startDay = Carbon::parse($this->log_date . ' ' . $this->dayBeginMark);
        $endDay = Carbon::parse($this->log_date . ' ' . $this->dayBeginMark)->addDay();
        return $this->hasMany(workTimeRegister::class, 'client_employee_id', 'client_employee_id')
            ->with('periods')
            ->where(function ($query) use ($startDay, $endDay) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->where('work_time_registers.start_time', '>=', $startDay);
                $query->where('work_time_registers.start_time', '<=', $endDay);
                $query->whereIn('work_time_registers.type', ['overtime_request', 'leave_request', 'congtac_request']);
            })
            ->orWhere(function ($query) use ($startDay, $endDay) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->where('work_time_registers.end_time', '>=', $startDay);
                $query->where('work_time_registers.end_time', '<=', $endDay);
                $query->whereIn('work_time_registers.type', ['overtime_request', 'leave_request', 'congtac_request']);
            })
            ->orWhere(function ($query) use ($startDay, $endDay) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->where('work_time_registers.start_time', '>=', $startDay);
                $query->where('work_time_registers.end_time', '<=', $endDay);
                $query->whereIn('work_time_registers.type', ['overtime_request', 'leave_request', 'congtac_request']);
            })->orWhere(function ($query) use ($startDay, $endDay) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->where('work_time_registers.start_time', '<=', $startDay);
                $query->where('work_time_registers.end_time', '>=', $endDay);
                $query->whereIn('work_time_registers.type', ['overtime_request', 'leave_request', 'congtac_request']);
            });
    }

    public function workTimeRegisterWhereLeaveRequest()
    {
        return $this->hasMany(workTimeRegister::class, 'client_employee_id', 'client_employee_id')
            ->where(function ($query) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->whereDate('work_time_registers.start_time', '>=', $this->log_date);
                $query->whereDate('work_time_registers.start_time', '<=', $this->log_date);
                $query->where('work_time_registers.type', 'leave_request');
            })
            ->orWhere(function ($query) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->whereDate('work_time_registers.end_time', '>=', $this->log_date);
                $query->whereDate('work_time_registers.end_time', '<=', $this->log_date);
                $query->where('work_time_registers.type', 'leave_request');
            })
            ->orWhere(function ($query) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->whereDate('work_time_registers.start_time', '>=', $this->log_date);
                $query->whereDate('work_time_registers.end_time', '<=', $this->log_date);
                $query->where('work_time_registers.type', 'leave_request');
            })->orWhere(function ($query) {
                $query->where('work_time_registers.client_employee_id', $this->client_employee_id);
                $query->where('work_time_registers.status', 'approved');
                $query->whereDate('work_time_registers.start_time', '<=', $this->log_date);
                $query->whereDate('work_time_registers.end_time', '>=', $this->log_date);
                $query->where('work_time_registers.type', 'leave_request');
            });
    }

    public function setIsHolidayAttribute($value)
    {
        if ($this->isHoliday === null) {
            $this->isHoliday = $value;
        }
    }

    public function getIsHolidayAttribute($value)
    {
        if ($this->isHoliday === null) {
            $clientEmployee = $this->clientEmployee;
            if ($clientEmployee->client_id && ClientYearHoliday::where("date", $this->log_date)->where('client_id', $clientEmployee->client_id)->first()) {
                if ($clientEmployee->date_of_entry && $clientEmployee->date_of_entry != '0000-00-00') {
                    $this->isHoliday = $this->log_date >= $clientEmployee->date_of_entry;
                } else {
                    $this->isHoliday = true;
                }
            } else {
                $this->isHoliday = false;
            }
        }

        return $this->isHoliday;
    }

    /**
     *
     * @param integer $force_update
     * @return void
     */
    public function setFlexibleAttribute($force_update)
    {
        // flexible_time is only set once time.
        if (
            $this->getOriginal('flexible_check_in')
            && $this->getOriginal('flexible_check_out')
            && !$force_update
        ) {
            return;
        }

        $clientEmployee = ClientEmployee::select(['timesheet_exception', 'work_schedule_group_template_id', 'client_id'])
            ->withCount(["worktimeRegisterPeriod" => function ($query) {
                $query->where('date_time_register', $this->log_date)
                    ->where('status', '!=', 'canceled_approved')
                    ->where('status', '!=', 'canceled');
            }])->where('id', $this->client_employee_id)->first();

        if (!$clientEmployee) {
            // Skip
            logger('Client employee has been deleted. ', ['id' => $this->client_employee_id]);
            return;
        }

        // if employees have any approves, we don't set flexible time for them.
        if ($clientEmployee->worktime_register_period_count) {
            return;
        }

        if ($this->skip_plan_flexible) {
            return;
        }

        $this->setFlexibleInOuT($clientEmployee);
    }

    public function setFlexibleInOuT($clientEmployee, $isNotCalCheckIn = false)
    {
        if (
            $clientEmployee->timesheet_exception == 'applied_flexible_time'
            && !empty($clientEmployee->client->clientWorkflowSetting->flexible_timesheet_setting['applied_flexible_time'])
        ) {
            $wsgTemplate = WorkScheduleGroupTemplate::find($clientEmployee->work_schedule_group_template_id);

            if(empty($wsgTemplate->check_in) || empty($wsgTemplate->check_out)) {
                return;
            }

            if (!$isNotCalCheckIn) {
                if ($this->check_in < $wsgTemplate->check_in) {
                    $this->flexible_check_in = null;
                    $this->flexible_check_out = null;
                    return;
                }
                if (empty($wsgTemplate->core_time_in)) {
                    return;
                }
                if ($this->check_in < $wsgTemplate->core_time_in) {
                    $this->flexible_check_in = $this->check_in;
                } else {
                    $this->flexible_check_in = $wsgTemplate->core_time_in;
                }
            }
            $wsCheckIn = strtotime($wsgTemplate->check_in);
            $wsCheckOut = strtotime($wsgTemplate->check_out);
            $flexibleCheckIn = strtotime($this->flexible_check_in);
            $this->flexible_check_out = date('H:i', $wsCheckOut + ($flexibleCheckIn - $wsCheckIn));
        }
    }

    public function timesheetShiftHistories()
    {
        return $this->hasMany(TimesheetShiftHistory::class, 'timesheet_id');
    }

    public function findTimeSheet($client_employee_id, $log_date)
    {
        return $this->where('client_employee_id', $client_employee_id)->whereDate('log_date', $log_date)->first();
    }

    public function isUsingMultiShift($clientWorkFlowSetting)
    {
        if ($clientWorkFlowSetting && $clientWorkFlowSetting->enable_multiple_shift && $this->timesheetShiftMapping->count()) {
            return true;
        }
        return false;
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    public function approves(): MorphMany
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $this->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
                        $clientEmployee->where('client_id', $user->client_id);
                    });
                default:
                    if ($user->hasAnyPermission(['manage-employee', 'manage-timesheet', 'CLIENT_REQUEST_TIMESHEET'])) {
                        return $this->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
                            $clientEmployee->where('client_id', $user->client_id);
                        });
                    }
                    return $this->where('client_employee_id', $user->clientEmployee->id);
            }
        } else {
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    return $query->belongToClientAssignedTo($user->iGlocalEmployee, 'clientEmployee');
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return $query;
            }
        }
        return $query;
    }

    public function recalculate()
    {
        $clientEmployee = ClientEmployee::where('id', $this->client_employee_id)->first();
        if (!$clientEmployee) {
            // Skip
            logger('Client employee has been deleted. ', ['id' => $this->client_employee_id]);
            return;
        }
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $clientEmployee->client_id)->first();
        if ($this->isUsingMultiShift($clientWorkFlowSetting)) {
            $this->calculateMultiTimesheet($clientWorkFlowSetting);
        } else {
            $this->oldRecalculate($clientEmployee);
        }
    }

    public function oldRecalculate($clientEmployee)
    {
        logger(__METHOD__ . ' start');

        $client = Client::with('clientWorkflowSetting')
            ->where('id', $clientEmployee->client_id)->first();

        if (!$client || !$client->clientWorkFlowSetting || !$client->clientWorkFlowSetting->enable_timesheet_rule) {
            // dont enforce any rule
            return;
        }

        $this->dayBeginMark = $client->clientWorkFlowSetting->getTimesheetDayBeginAttribute();
        // Get from client config
        $dayStart = Carbon::parse($this->log_date . ' ' . $this->dayBeginMark);
        $dayEnd = $dayStart->clone()->addDay();
        $dayPeriod = PeriodHelper::makePeriod($dayStart, $dayEnd);
        $workScheduleGroupTemplate = $clientEmployee->workScheduleGroupTemplate;

        // Block tính giờ tối thiểu
        $timesheetMinTimeBlock = $client->timesheet_min_time_block;
        $otMinTimeBlock = $client->ot_min_time_block;

        // set 0 all
        $this->mission_hours = 0;
        $this->wfh_hours = 0;
        $this->working_hours = 0;
        $this->shift = 0;
        $this->rest_hours = 0;
        $this->outside_working_hours = 0;
        $this->other_business_hours = 0;
        $this->paid_leave_hours = 0;
        $this->unpaid_leave_hours = 0;
        $this->mission_road_hours = 0;
        $this->mission_airline_hours = 0;

        /** @var WorkSchedule $workSchedule */
        $workSchedule = $this->workSchedule = WorkSchedule::query()
            ->where('client_id', $clientEmployee->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) use ($clientEmployee) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $clientEmployee->work_schedule_group_template_id
                    );
                }
            )
            ->where('schedule_date', $this->log_date)
            ->with('workScheduleGroup')
            ->first();

        if (empty($workSchedule)) {
            logger(
                'Timesheet@recalculate WorkSchedule is not set',
                [
                    'schedule_date' => $this->log_date,
                    'work_schedule_group_template_id' => $clientEmployee->work_schedule_group_template_id,
                ]
            );
            return;
        }

        $workSchedule->workScheduleGroup->load('workScheduleGroupTemplate');
        $this->workScheduleGroupTemplate = $workSchedule->workScheduleGroup->workScheduleGroupTemplate;
        /** @var WorkScheduleGroupTemplate $wsgTemplate */
        $wsgTemplate = $workSchedule->workScheduleGroup->workScheduleGroupTemplate;
        if (empty($wsgTemplate)) {
            logger(
                'Timesheet@recalculate WorkSchedule without WorkScheduleGroupTemplate',
                [
                    'timesheet_id' => $this->id,
                    'schedule_date' => $this->log_date,
                    'work_schedule_group_template_id' => $clientEmployee->work_schedule_group_template_id,
                ]
            );
            return;
        }

        //using for creating case.
        $this->work_schedule_group_template_id = $this->work_schedule_group_template_id ?? $clientEmployee->work_schedule_group_template_id;

        $workSchedule = $this->getShiftWorkSchedule($workSchedule);

        // Invalid checkin / checkout recovery
        if ($this->check_in >= '24:00') {
            $this->check_in = '23:58';
        }
        if ($this->check_out >= '24:00') {
            $this->check_out = '23:59';
        }

        [$checkIn, $checkOut] = $this->getCheckInOutCarbonAttribute($clientEmployee, $workSchedule, $timesheetMinTimeBlock, $client->clientWorkFlowSetting->flexible_timesheet_setting ?? []);
        // check if this day is off day
        $isRestDay = $workSchedule->is_off_day || $workSchedule->is_holiday;

        // WorkSchedule config
        $workingHours = 0;

        // Timesheet period
        $timesheetPeriod = Period::make($checkIn, $checkOut, Precision::SECOND);
        logger(__METHOD__ . ": employee: ", ["timesheet_id" => $this->id, "employee_id" => $this->client_employee_id]);
        logger(__METHOD__ . ": timesheetPeriod: ", ["start" => $checkIn, "end" => $checkOut]);

        // Schedule period
        if (empty($workSchedule->check_in)) {
            $workSchedule->check_in = $wsgTemplate->check_in;
        }
        if (empty($workSchedule->check_out)) {
            $workSchedule->check_out = $wsgTemplate->check_out;
        }
        if ($workSchedule->is_off_day) {
            // package Spatie/Period vẫn cần một khoản để tính overlap
            // dummy period
            $wsPeriod = Period::make(
                $this->log_date . ' 00:00:00',
                $this->log_date . ' 00:00:01',
                Precision::SECOND
            );
        } else {
            $wsPeriod = $workSchedule->getWorkSchedulePeriodAttribute();
        }
        logger(__METHOD__ . ": wsPeriod: ", ["start" => $wsPeriod->getStart(), "end" => $wsPeriod->getEnd()]);

        // Reset period
        $restPeriod = $workSchedule->rest_period;
        $restHours = $this->restHours = PeriodHelper::countHours($restPeriod);

        if ($client->clientWorkflowSetting->enable_overtime_request) {
            $this->validateWorkTimeRegisterTimesheetByCheckInOut($dayPeriod, $otMinTimeBlock, $client->clientWorkflowSetting->flexible_timesheet_setting, $this->dayBeginMark);
            $this->overtime_hours = 0;
            $this->midnight_overtime_hours = 0;
            $this->makeup_hours = 0;
            $this->reCalculateOT();
        }

        // Request nghi phep
        $requests = collect();
        $khlRequests = collect();
        $missionRequests = collect();
        $wfhRequests = collect();
        $outsideWorkingRequest = collect();
        $specialOtherRequests = collect();
        $missionAirlineRequests = collect();
        $missionRoadRequests = collect();
        {
            $wtrs = $this->getWorktimeRegistersForDay(
                "leave_request",
                [
                    'authorized_leave',
                    'special_leave',
                ],
                $clientEmployee,
                $dayStart,
                $dayEnd
            );
            /** @var WorkTimeRegisterPeriod[]|Collection $requests */
            foreach ($wtrs as $wtr) {
                $requests = $requests->concat($wtr->periods);
            }

            //
            // request KHL
            //
            $khlWtrs = $this->getWorktimeRegistersForDay(
                "leave_request",
                [
                    'unauthorized_leave',
                ],
                $clientEmployee,
                $dayStart,
                $dayEnd
            );
            /** @var WorkTimeRegisterPeriod[]|Collection $requests */
            foreach ($khlWtrs as $wtr) {
                $khlRequests = $khlRequests->concat($wtr->periods);
            }

            /**
             * Cong tac
             */
            $missionWtrs = $this->getWorktimeRegistersForDay(
                "congtac_request",
                null,
                $clientEmployee,
                $dayStart,
                $dayEnd
            );

            $missionBusinessWtr = $missionWtrs->where('sub_type', 'business_trip');

            /** @var WorkTimeRegisterPeriod[]|Collection $missionRequests */
            foreach ($missionBusinessWtr as $wtr) {
                $missionRequests = $missionRequests->concat($wtr->periods->where('date_time_register', $this->log_date));
            }

            foreach ($missionBusinessWtr->where('category', 'airline') as $wtr) {
                $missionAirlineRequests = $missionAirlineRequests->concat($wtr->periods->where('date_time_register', $this->log_date));
            }

            foreach ($missionBusinessWtr->where('category', 'road') as $wtr) {
                $missionRoadRequests = $missionRoadRequests->concat($wtr->periods->where('date_time_register', $this->log_date));
            }

            foreach ($missionWtrs->where('sub_type', 'wfh') as $wtr) {
                $wfhRequests = $wfhRequests->concat($wtr->periods->where('date_time_register', $this->log_date));
            }
            foreach ($missionWtrs->where('sub_type', 'outside_working') as $wtr) {
                $outsideWorkingRequest = $outsideWorkingRequest->concat($wtr->periods->where('date_time_register', $this->log_date));
            }
            foreach ($missionWtrs->where('sub_type', 'other') as $wtr) {
                $specialOtherRequests = $specialOtherRequests->concat($wtr->periods->where('date_time_register', $this->log_date));
            }
        }

        $missingHours = 0;
        $khlHours = 0;
        if (!$isRestDay) {
            $joinPeriods = collect();
            // Spatie Period BUG, nếu không có khoản nào giao nhau, diff() sẽ bị thành gap(), lỗi của Spatie 1.6
            $joinPeriods->add(Period::make($wsPeriod->getStart(), $wsPeriod->getStart(), Precision::SECOND));

            $khlPeriods = collect();
            $hlPeriods = collect();
            $missionPeriods = collect();
            $missionRoadPeriods = collect();
            $missionAirlinePeriods = collect();
            $wfhPeriods = collect();
            $outsidePeriods = collect();
            $specialOtherPeriods = collect();
            if ($client->clientWorkflowSetting->enable_leave_request) {
                $khlPeriods = $khlRequests->count() ?
                    $this->getPeriodFromWtrPeriods($khlRequests, $wsPeriod, $dayPeriod)
                    : collect();
                $hlPeriods = $requests->count() ?
                    $this->getPeriodFromWtrPeriods($requests, $wsPeriod, $dayPeriod)
                    : collect();
                $missionPeriods = $missionRequests->count() ?
                    $this->getPeriodFromWtrPeriods($missionRequests, $wsPeriod, $dayPeriod, false)
                    : collect();
                $wfhPeriods = $wfhRequests->count() ?
                    $this->getPeriodFromWtrPeriods($wfhRequests, $wsPeriod, $dayPeriod, false)
                    : collect();
                $outsidePeriods = $outsideWorkingRequest->count() ?
                    $this->getPeriodFromWtrPeriods($outsideWorkingRequest, $wsPeriod, $dayPeriod, false)
                    : collect();
                $specialOtherPeriods = $specialOtherRequests->count() ?
                    $this->getPeriodFromWtrPeriods($specialOtherRequests, $wsPeriod, $dayPeriod, false)
                    : collect();
            }
            $joinPeriods = $joinPeriods->concat($khlPeriods)
                ->concat($hlPeriods)
                ->concat($missionPeriods)
                ->concat($wfhPeriods)
                ->concat($outsidePeriods)
                ->concat($specialOtherPeriods);

            $joinPeriods = $joinPeriods->add($timesheetPeriod);

            $missingPeriods = call_user_func_array([$wsPeriod, 'diff'], $joinPeriods->toArray());
            // Validate missing in core time and override missing hour core time
            $this->overrideMissingCoreTime($joinPeriods, $restPeriod);

            // Update paid_leave_hours
            $paidLeaveHours = $hlPeriods->reduce(function ($carry, $period) use ($restPeriod) {
                $overlapWithRest = $restPeriod->overlapSingle($period);
                $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRest)) / 60,
                    4
                );
                return $carry;
            }, 0);
            $this->paid_leave_hours = round($paidLeaveHours, 2);

            // Update unpaid_leave_hours
            $khlHours = $khlPeriods->reduce(function ($carry, $period) use ($restPeriod) {
                $overlapWithRest = $restPeriod->overlapSingle($period);
                $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRest)) / 60,
                    4
                );
                return $carry;
            }, 0);
            $this->unpaid_leave_hours = round($khlHours, 2);

            $leaveHours = (floatval($this->paid_leave_hours) + floatval($this->unpaid_leave_hours));
            $overlapMethod = function ($carry, $period) use ($leaveHours) {
                if ($leaveHours > 0) {
                    $period->run_overlap_leave = true;
                }
                $carry += $period->duration;
                return max($carry, 0);
            };

            // Update hours of business type when
            $this->mission_hours = $missionRequests->reduce($overlapMethod, 0);
            $this->mission_airline_hours = $missionAirlineRequests->reduce($overlapMethod, 0);
            $this->mission_road_hours = $missionRoadRequests->reduce($overlapMethod, 0);
            $this->wfh_hours = $wfhRequests->reduce($overlapMethod, 0);
            $this->outside_working_hours = $outsideWorkingRequest->reduce($overlapMethod, 0);
            $this->other_business_hours = $specialOtherRequests->reduce($overlapMethod, 0);

            // Lack of hours when the user does not apply(leave,business)
            $missingHours = $missingPeriods->reduce(function ($carry, $period) use ($restPeriod) {
                $overlapWithRest = $restPeriod->overlapSingle($period);
                $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRest)) / 60,
                    4
                );
                return $carry;
            }, 0);

            // Calculation again working hours
            $workingHours = $workSchedule->workHours - $missingHours - $paidLeaveHours - $khlHours;
            $workingHours = max($workingHours, 0);
            $workingHours = round($workingHours, 2, PHP_ROUND_HALF_DOWN);
        } else {
            if ($workSchedule->is_holiday) {
                $workingHours = $workSchedule->workHours;
            }
        }

        // Update working_hours
        // $this->working_hours = floor(($workingHours * 60) / $timesheetMinTimeBlock) / 60 * $timesheetMinTimeBlock;
        $this->working_hours = $workingHours;
        if ($this->working_hours == 0 || $isRestDay) {
            $this->missing_hours_in_core_time = 0;
        }
        // Update rest_hours
        $this->rest_hours = $restHours;
        // Update shift
        if ($workSchedule->shift && ($this->working_hours + $this->paid_leave_hours) == $workSchedule->workHours) {
            $this->shift = $workSchedule->shift;
        }

        // Update work_status
        if ($workSchedule->is_holiday) {
            $status = self::STATUS_NGHI_LE;
        } elseif ($workSchedule->is_off_day) {
            $status = self::STATUS_NGHI_CUOI_TUAN;
        } elseif ($this->working_hours == 0 && $this->paid_leave_hours > 0 && $missingHours == 0) {
            $status = self::STATUS_NGHI_PHEP_HL;
        } elseif ($this->working_hours == 0 && $this->paid_leave_hours == 0 && $khlHours > 0) {
            $status = self::STATUS_NGHI_PHEP_KHL;
        } else {
            $status = self::STATUS_DI_LAM;
        }
        $this->work_status = $status;

        // Create compensatory or OT working when pressing "update calendar" button
        $clientSetting = $client->clientWorkFlowSetting;
        if (
            ($this->isUpdateWorkSchedule && $workScheduleGroupTemplate->enable_makeup_or_ot_form && $checkOut->isAfter($wsPeriod->getEnd()))
            && (($clientSetting->enable_auto_generate_ot && $clientSetting->approval_system_assigment_id) ||
                ($clientSetting->enable_makeup_request_form && $clientSetting->auto_create_makeup_request_form))
        ) {
            $requestPeriod = Period::make($wsPeriod->getEnd(), $checkOut, Precision::SECOND);
            $requestTime = PeriodHelper::countMinutes($requestPeriod);
            $end = Carbon::createFromTimeString($this->dayBeginMark, 'Asia/Ho_Chi_Minh')->addDay();
            // Delay is existed and check_out time is enough or more than 1 block
            if ($requestTime >= $timesheetMinTimeBlock) {
                $delay = strtotime($this->log_date) >= strtotime(Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d')) ? Carbon::now('Asia/Ho_Chi_Minh')->diffInMinutes($end, false) : 0;
                $type = $clientSetting->enable_auto_generate_ot ? Constant::OVERTIME_TYPE : Constant::MAKEUP_TYPE;
                dispatch(new AutoGenerateOTRequest($this->id, $type))->delay($delay);
            }
        }
    }

    public function setIsUpdateWorkScheduleAttribute($value)
    {
        if ($value) {
            $this->isUpdateWorkSchedule = true;
        }
    }

    /**
     * @param \App\Models\ClientEmployee $clientEmployee
     * @param string $wsStart
     * @param string $wsEnd
     *
     * @return \App\Models\WorktimeRegister[]
     */
    public function getWorktimeRegistersForDay(
        string         $type,
        ?array         $subTypes,
        ClientEmployee $clientEmployee,
        string         $wsStart,
        string         $wsEnd
    )
    {
        $query = WorktimeRegister::query()
            ->with('periods')
            ->where('client_employee_id', $clientEmployee->id)
            ->isApproved()
            ->whereType($type)
            ->where(function ($subQuery) use ($wsStart, $wsEnd) {
                $subQuery->whereBetween('start_time', [
                    $wsStart,
                    $wsEnd,
                ])
                    ->orWhereBetween('end_time', [
                        $wsStart,
                        $wsEnd,
                    ])
                    ->orWhere(function ($query) use ($wsStart) {
                        $query->where('start_time', '<=', $wsStart)
                            ->where('end_time', '>=', $wsStart);
                    })
                    ->orWhere(function ($query) {
                        $query->whereDate('start_time', $this->log_date)
                            ->orWhereDate('end_time', $this->log_date);
                    });
            });

        if ($subTypes) {
            $query->whereIn('sub_type', $subTypes);
        }
        return $query->get();
    }

    /**
     * Common pre-process requested periods
     *
     * @param $requests
     * @param $wsPeriod Period
     * @param $wsStart
     * @param $wsEnd
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPeriodFromWtrPeriods($requests, Period $wsPeriod, Period $dayPeriod, $overlap = true): Collection
    {
        $collection = collect();
        foreach ($requests as $v) {
            /** @var \App\Models\WorkTimeRegisterPeriod $v */


            if ($v->type_register == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                if ($v->date_time_register != $this->log_date) {
                    // Request is not for this day, lets skip
                    continue;
                }
                // nghỉ cả ngày, tức = $wsPeriod
                $nghiPhepHopLePeriod = clone $wsPeriod;
            } else {
                // nghỉ theo đoạn, overlap với WS
                $requestPeriod = $v->getPeriod();
                $nghiPhepHopLePeriod = $requestPeriod;
                if ($overlap) {
                    $nghiPhepHopLePeriod = $requestPeriod->overlapSingle($wsPeriod);
                }
                // đơn công tác, WFH -> không lấy overlap
                // kiểm tra ngày xin có rơi vào ngày làm việc không
                if (!$dayPeriod->overlapsWith($requestPeriod)) {
                    continue;
                }
            }
            if ($nghiPhepHopLePeriod) {
                $collection = $collection->add($nghiPhepHopLePeriod);
            }
        }
        return $collection;
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }

    public function getActualWorkingHours()
    {
        if (!$this->check_in || !$this->check_out) {
            return 0;
        }

        $clientEmployee = ClientEmployee::find($this->client_employee_id);
        $workSchedule = $this->workSchedule = WorkSchedule::query()
            ->where('client_id', $clientEmployee->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) use ($clientEmployee) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $clientEmployee->work_schedule_group_template_id
                    );
                }
            )
            ->where('schedule_date', $this->log_date)
            ->with('workScheduleGroup')
            ->first();
        $diffHours = 0;
        $date = $this->log_date;
        $checkIn = $date . " " . $this->check_in . ":00";
        $checkOut = $date . " " . $this->check_out . ":00";

        $diffSeconds = strtotime($checkOut) - strtotime($checkIn);
        if (is_numeric($diffSeconds) && $diffSeconds > 0) {
            $diffHours += floatval($diffSeconds / 3600);
        }

        // Subtract break period
        if ($workSchedule && $workSchedule->start_break && $workSchedule->end_break) {
            $startBreak = $date . " " . $workSchedule->start_break . ":00";
            $endBreak = $date . " " . $workSchedule->end_break . ":00";
            $breakPeriod = Period::make($startBreak, $endBreak, Precision::SECOND);
            $workingPeriod = Period::make($checkIn, $checkOut, Precision::SECOND);
            $overlap = $breakPeriod->overlapSingle($workingPeriod);
            if ($overlap) {
                $overlapDuration = PeriodHelper::countHours($overlap);
                $overlap = $breakPeriod->overlapSingle($workingPeriod);
                $diffHours -= $overlapDuration;
            }
        }

        return round($diffHours, 1);
    }

    /**
     * Get override work schedule whether shift enabled
     * @param $defaultWorkSchedule
     * @param $ignoreFlexible
     */
    public function getShiftWorkSchedule($defaultWorkSchedule, $ignoreFlexible = 0)
    {
        // immutable
        $workSchedule = clone $defaultWorkSchedule;
        $workSchedule->shift_enabled = $this->shift_enabled;
        $workSchedule->leave_hours = (floatval($this->paid_leave_hours) + floatval($this->unpaid_leave_hours));
        // override shift
        if ($this->is_holiday) {
            $wsgTemplate = $workSchedule->workScheduleGroup->workScheduleGroupTemplate;
            $workSchedule->is_holiday = 1;
            $workSchedule->is_off_day = 0;
            $workSchedule->check_in = $workSchedule->check_in ?: $wsgTemplate->check_in;
            $workSchedule->check_out = $workSchedule->check_out ?: $wsgTemplate->check_out;
            $workSchedule->start_break = $workSchedule->start_break ?: $wsgTemplate->start_break;
            $workSchedule->end_break = $workSchedule->end_break ?: $wsgTemplate->end_break;
        } else if ($this->shift_enabled) {
            if ($this->shift_is_holiday) {
                $wsgTemplate = $workSchedule->workScheduleGroup->workScheduleGroupTemplate;
                $workSchedule->is_holiday = $this->shift_is_holiday;
                $workSchedule->is_off_day = 0;
                $workSchedule->check_in = $workSchedule->check_in ?: $wsgTemplate->check_in;
                $workSchedule->check_out = $workSchedule->check_out ?: $wsgTemplate->check_out;
                $workSchedule->start_break = $workSchedule->start_break ?: $wsgTemplate->start_break;
                $workSchedule->end_break = $workSchedule->end_break ?: $wsgTemplate->end_break;
            } else {
                $workSchedule->check_in = $this->shift_check_in;
                $workSchedule->check_out = $this->shift_check_out;
                $workSchedule->start_break = $this->shift_break_start ?: "00:00";
                $workSchedule->end_break = $this->shift_break_end ?: "00:00";
                $workSchedule->next_day = $this->shift_next_day;
                $workSchedule->is_off_day = $this->shift_is_off_day;
                $workSchedule->shift = $this->shift_shift;
            }
        } elseif (!$this->timesheetShiftMapping->isEmpty()) {
            $wsgTemplate = $workSchedule->workScheduleGroup->workScheduleGroupTemplate;
            $workSchedule->is_holiday = 0;
            $workSchedule->is_off_day = 0;
            $workSchedule->shift_enabled = true;
            $workSchedule->check_in = $workSchedule->check_in ?: $wsgTemplate->check_in;
            $workSchedule->check_out = $workSchedule->check_out ?: $wsgTemplate->check_out;
            $workSchedule->start_break = $workSchedule->start_break ?: $wsgTemplate->start_break;
            $workSchedule->end_break = $workSchedule->end_break ?: $wsgTemplate->end_break;
        } elseif (
            $this->flexible_check_in
            && $this->flexible_check_out
            && !$workSchedule->is_off_day
            && !$workSchedule->is_holiday
            && !$ignoreFlexible
        ) {
            $workSchedule->check_in = $this->flexible_check_in;
            $workSchedule->check_out = $this->flexible_check_out;
            $workSchedule->next_day = 0;
        }
        return $workSchedule;
    }

    /**
     * @param $wtrs \App\Models\WorkTimeRegisterPeriod optional
     * @param $clientEmployee \App\Models\ClientEmployee optional
     * @param $otMinTimeBlock int optional
     * @param $flexibleTimesheetSetting array optional
     *
     * @return array
     */
    public function getCheckInOutCarbonByRequestPeriod(
        $wtrs,
        $clientEmployee = null,
        $otMinTimeBlock = null,
        $flexibleTimesheetSetting = []
    ): array
    {
        if (!$clientEmployee) {
            /** @var \App\Models\ClientEmployee $clientEmployee */
            $clientEmployee = $this->clientEmployee;
        }
        if (!$otMinTimeBlock) {
            $otMinTimeBlock = $clientEmployee->client->ot_min_time_block ?? 1;
        }
        if (!$flexibleTimesheetSetting) {
            $flexibleTimesheetSetting = $clientEmployee->client->clientWorkflowSetting->flexible_timesheet_setting ?? [];
        }

        switch ($clientEmployee->timesheet_exception) {
            case "checkin":
                if (!empty($flexibleTimesheetSetting['enable_check_in_out'])) {
                    $checkIn = Carbon::parse($this->log_date . ' ' . $wtrs->start_time);
                }
                break;
            case "checkout":
                if (!empty($flexibleTimesheetSetting['enable_check_in_out'])) {
                    $checkOut = $wtrs->next_day
                        ? Carbon::parse($this->log_date . ' ' . $wtrs->end_time)->addDay()
                        : Carbon::parse($this->log_date . ' ' . $wtrs->end_time);
                }
                break;
            case "all":
                if (!empty($flexibleTimesheetSetting['enable_check_in_out'])) {
                    $checkIn = Carbon::parse($this->log_date . ' ' . $wtrs->start_time);
                    $checkOut = $wtrs->next_day
                        ? Carbon::parse($this->log_date . ' ' . $wtrs->end_time)->addDay()
                        : Carbon::parse($this->log_date . ' ' . $wtrs->end_time);
                }
                break;
            default:
                break;
        }

        if ($this->check_in && !isset($checkIn)) {
            $checkIn = $this->checkInByActualTime($wtrs->start_time, $otMinTimeBlock);
        }

        if ($this->check_out && !isset($checkOut)) {
            $checkOut = $this->checkOutByActualTime($wtrs->end_time, $otMinTimeBlock, $wtrs->next_day);
        }

        if (!isset($checkIn) || !isset($checkOut) || $checkIn->isAfter($checkOut)) {
            // Nếu vẫn thiếu giờ, fallback về giờ dummy (không đi làm)
            $checkIn = Carbon::parse($this->log_date . ' 00:00:00');
            $checkOut = Carbon::parse($this->log_date . ' 00:00:01');
        }

        return [$checkIn, $checkOut];
    }

    /**
     * @param $clientEmployee \App\Models\ClientEmployee optional
     * @param $workSchedule WorkSchedule optional
     * @param $timesheetMinTimeBlock int optional
     * @param $flexibleTimesheetSetting array optional
     *
     * @return array
     */
    public function getCheckInOutCarbonAttribute(
        $clientEmployee = null,
        $workSchedule = null,
        $timesheetMinTimeBlock = null,
        $flexibleTimesheetSetting = []
    ): array
    {
        if (!$clientEmployee) {
            /** @var \App\Models\ClientEmployee $clientEmployee */
            $clientEmployee = $this->clientEmployee;
        }
        if (!$workSchedule) {
            /** @var WorkSchedule $workSchedule */
            $workSchedule = $clientEmployee->getWorkSchedule($this->log_date);
            $workSchedule = $this->getShiftWorkSchedule($workSchedule);
        }
        if (!$timesheetMinTimeBlock) {
            $timesheetMinTimeBlock = $clientEmployee->client->timesheet_min_time_block ?? 1;
        }
        if (!$flexibleTimesheetSetting) {
            $flexibleTimesheetSetting = $clientEmployee->client->clientWorkflowSetting->flexible_timesheet_setting ?? [];
        }

        $this->missing_hours_in_core_time = 0;

        switch ($clientEmployee->timesheet_exception) {
            case "checkin":
                /**
                 *  Nếu có rule không cần checkin:
                 *   - nếu không nhập check in thì dùng checkin của ws
                 *   - nếu có nhập check in, kiểm tra xem nó có trễ hơn ws không,
                 *     nếu có trễ thì du di cho nó checkin đúng giờ
                 */
                if (
                    !empty($flexibleTimesheetSetting['enable_check_in_out'])
                    && (!$this->check_in || $this->check_in > $workSchedule->check_in)
                ) {
                    $checkIn = Carbon::parse($this->log_date . ' ' . $workSchedule->check_in);
                }
                break;
            case "checkout":
                /**
                 *  Nếu có rule không cần check out:
                 *   - nếu không nhập check out thì dùng check out của ws
                 *   - nếu có nhập check out, kiểm tra xem nó có trễ hơn ws không,
                 *     nếu có trễ thì du di cho nó check out đúng giờ
                 */
                if (
                    !empty($flexibleTimesheetSetting['enable_check_in_out'])
                    && (!$this->check_out || $this->check_out < $workSchedule->check_out)
                ) {
                    $checkOut = $workSchedule->next_day
                        ? Carbon::parse($this->log_date . ' ' . $workSchedule->check_out)->addDay()
                        : Carbon::parse($this->log_date . ' ' . $workSchedule->check_out);
                }
                break;
            case "all":
                /**
                 *   - nếu không nhập check in thì dùng checkin của ws
                 *   - nếu có nhập check in, kiểm tra xem nó có trễ hơn ws không,
                 *     nếu có trễ thì du di cho nó checkin đúng giờ
                 */
                if (
                    !empty($flexibleTimesheetSetting['enable_check_in_out'])
                    && (!$this->check_in || $this->check_in > $workSchedule->check_in)
                ) {
                    $checkIn = Carbon::parse($this->log_date . ' ' . $workSchedule->check_in);
                }

                /**
                 *   - nếu không nhập check out thì dùng check out của ws
                 *   - nếu có nhập check out, kiểm tra xem nó có trễ hơn ws không,
                 *     nếu có trễ thì du di cho nó check out đúng giờ
                 */
                if (
                    !empty($flexibleTimesheetSetting['enable_check_in_out'])
                    && (!$this->check_out || $this->check_out < $workSchedule->check_out)
                ) {
                    $checkOut = $workSchedule->next_day
                        ? Carbon::parse($this->log_date . ' ' . $workSchedule->check_out)->addDay()
                        : Carbon::parse($this->log_date . ' ' . $workSchedule->check_out);
                }
                break;
            case "applied_core_time":
                $wsgTemplate = WorkScheduleGroupTemplate::find($clientEmployee->work_schedule_group_template_id);
                if (
                    !empty($flexibleTimesheetSetting['applied_core_time'])
                    && !$this->flexible_check_in
                    && !$this->flexible_check_out
                    && $wsgTemplate->core_time_in
                    && $wsgTemplate->core_time_out
                    && $this->check_in
                    && $this->check_out
                    && !$this->shift_enabled
                ) {
                    /**
                     * $compromisedMinutes = (core_time_in - check_in)
                     * $diff = (actual_check_in - break_end)
                     * actual_check_in is between schedule_in and schedule_out.
                     *     - Case 1: checkin is between work_schedule_in and core_time_in   => return checkin is schedule_in.
                     *     - Case 2: checkin is between start_break and end_break           => return checkin is start_break - $compromisedMinutes.
                     *     - Case 3: checkin is between end_break and (end_break + $compromisedMinutes) => return checkin is start_break - ($compromisedMinutes - $diff)
                     *     - Default => return actual_check_in - $compromisedMinutes
                     */
                    if ($this->check_in > $workSchedule->check_in && $this->check_in < $workSchedule->check_out) {
                        $actualCheckIn = Carbon::parse($this->log_date . ' ' . $this->check_in);
                        if ($this->start_next_day) {
                            $actualCheckIn->addDay();
                        }
                        $coreTimeIn = Carbon::parse($this->log_date . ' ' . $wsgTemplate->core_time_in);
                        $scheduleIn = Carbon::parse($this->log_date . ' ' . $workSchedule->check_in);
                        $compromisedMinutes = $coreTimeIn->diffInMinutes($scheduleIn);

                        /** Case 1 */
                        if ($actualCheckIn->lessThanOrEqualTo($coreTimeIn)) {
                            $checkIn = $scheduleIn;
                        } else {
                            if ($workSchedule->start_break && $workSchedule->end_break) {
                                $breakStart = Carbon::parse($this->log_date . ' ' . $workSchedule->start_break);
                                $breakEnd = Carbon::parse($this->log_date . ' ' . $workSchedule->end_break);
                                /** Case 2 */
                                if ($actualCheckIn->isBetween($breakStart, $breakEnd)) {
                                    $checkIn = $breakStart->subMinutes($compromisedMinutes);
                                }
                                /** Case 3 */
                                elseif (($diff = $actualCheckIn->diffInMinutes($breakEnd)) < $compromisedMinutes) {
                                    $checkIn = $breakStart->subMinutes($compromisedMinutes - $diff);
                                }
                                /** Default */
                                else {
                                    $checkIn = $actualCheckIn->subMinutes($compromisedMinutes);
                                }
                            }
                            /** Default */
                            else {
                                $checkIn = $actualCheckIn->subMinutes($compromisedMinutes);
                            }
                            $checkIn = Carbon::createFromTimestamp($this->floorByTimestamp($scheduleIn->timestamp, $checkIn->timestamp, $timesheetMinTimeBlock * 60));
                        }
                    }

                    /**
                     * $compromisedMinutes = (schedule_out - core_time_out)
                     * $diff = (break_start - actual_check_out)
                     * actual_check_out is between schedule_in and schedule_out.
                     *     - Case 1: checkout is between core_time_out and schedule_out     => return checkout is schedule_out.
                     *     - Case 2: checkout is between start_break and end_break          => return checkout is end_break + $compromisedMinutes.
                     *     - Case 3: checkout is between (start_break - $compromisedMinutes) and start_break  => return checkout is end_break + ($compromisedMinutes - $diff)
                     *     - Default => return actual_check_out + $compromisedMinutes
                     */
                    if ($this->check_out < $workSchedule->check_out && $this->check_out > $workSchedule->check_in) {
                        $actualCheckOut = Carbon::parse($this->log_date . ' ' . $this->check_out);
                        if ($this->next_day) {
                            $actualCheckOut->addDay();
                        }
                        $coreTimeOut = Carbon::parse($this->log_date . ' ' . $wsgTemplate->core_time_out);
                        $scheduleOut = Carbon::parse($this->log_date . ' ' . $workSchedule->check_out);
                        $compromisedMinutes = $coreTimeOut->diffInMinutes($scheduleOut);

                        /** Case 1 */
                        if ($actualCheckOut->greaterThanOrEqualTo($coreTimeOut)) {
                            $checkOut = $scheduleOut;
                        } else {
                            if ($workSchedule->start_break && $workSchedule->end_break) {
                                $breakStart = Carbon::parse($this->log_date . ' ' . $workSchedule->start_break);
                                $breakEnd = Carbon::parse($this->log_date . ' ' . $workSchedule->end_break);

                                /** Case 2 */
                                if ($actualCheckOut->isBetween($breakStart, $breakEnd)) {
                                    $checkOut = $breakEnd->addMinutes($compromisedMinutes);
                                }
                                /** Case 3 */
                                elseif (($diff = $breakStart->diffInMinutes($actualCheckOut)) < $compromisedMinutes) {
                                    $checkOut = $breakEnd->addMinutes($compromisedMinutes - $diff);
                                }
                                /** Default */
                                else {
                                    $checkOut = $actualCheckOut->addMinutes($compromisedMinutes);
                                }
                            }
                            /** Default */
                            else {
                                $checkOut = $actualCheckOut->addMinutes($compromisedMinutes);
                            }
                            $checkOut = Carbon::createFromTimestamp($this->ceilByTimestamp($scheduleOut->timestamp, $checkOut->timestamp, $timesheetMinTimeBlock * 60));
                        }
                    }
                }
                break;

            case "applied_flexible_time":
                // Set missing hours in the core time, not set checkin, checkout
                $wsgTemplate = WorkScheduleGroupTemplate::find($clientEmployee->work_schedule_group_template_id);
                if (
                    !empty($flexibleTimesheetSetting['applied_flexible_time'])
                    && $wsgTemplate->core_time_in
                    && $wsgTemplate->core_time_out
                    && $this->check_in
                    && $this->check_out
                    && !$this->shift_enabled
                ) {
                    $coreTimeOut = strtotime($this->log_date . ' ' . $wsgTemplate->core_time_out);
                    if ($this->check_in > $workSchedule->check_in) {
                        $actualCheckIn = $this->start_next_day ? strtotime($this->log_date . ' ' . $this->check_in . '+1 day') : strtotime($this->log_date . ' ' . $this->check_in);
                        $actualCheckInTimestamp = Carbon::createFromTimestamp($actualCheckIn);
                        $hourAndMinuteAllowBlock = $this->roundCeilToNearestBlock($actualCheckInTimestamp->format('H:i'), $timesheetMinTimeBlock);
                        $coreTimeIn = strtotime($this->log_date . ' ' . $wsgTemplate->core_time_in);
                        $coreTimeCheckInString = Carbon::createFromTimestamp($coreTimeIn)->format('Y-m-d H:i:s');
                        $endMissingCheckIn = $this->log_date . ' ' . $hourAndMinuteAllowBlock . ':00';
                        if ($actualCheckIn > $coreTimeIn && $coreTimeIn < strtotime($endMissingCheckIn) && $actualCheckIn <= $coreTimeOut) {
                            $workScheduleIn = strtotime($this->log_date . ' ' . $workSchedule->check_in);
                            $checkInLate = $actualCheckIn - $coreTimeIn;
                            $actualCheckIn = $workScheduleIn + $checkInLate;
                            $checkInWithTimeBlock = $this->floorByTimestamp($workScheduleIn, $actualCheckIn, $timesheetMinTimeBlock * 60);
                            // Set missing hours of check-in in the core time
                            $this->missing_hours_in_core_time += ($checkInWithTimeBlock - $workScheduleIn) / 3600;
                            // Create period
                            $this->periodMissingCoretime['check_in'] = Period::make(
                                $coreTimeCheckInString,
                                $endMissingCheckIn,
                                Precision::SECOND
                            );
                        }
                    }

                    if ($this->check_out < $workSchedule->check_out) {
                        $actualCheckOut = $this->next_day ? strtotime($this->log_date . ' ' . $this->check_out . '+1 day') : strtotime($this->log_date . ' ' . $this->check_out);
                        $actualCheckOutTimestamp = Carbon::createFromTimestamp($actualCheckOut);
                        $hourAndMinuteAllowBlock = $this->roundCeilToNearestBlock($actualCheckOutTimestamp->format('H:i'), $timesheetMinTimeBlock);

                        $coreTimeCheckOutString = Carbon::createFromTimestamp($coreTimeOut)->format('Y-m-d H:i:s');
                        $startMissingCheckOut = $this->log_date . ' ' . $hourAndMinuteAllowBlock . ':00';
                        if ($actualCheckOut < $coreTimeOut && strtotime($startMissingCheckOut) < $coreTimeOut) {
                            $workScheduleOut = $workSchedule->next_day ? strtotime($this->log_date . ' ' . $workSchedule->check_out . '+1 day') : strtotime($this->log_date . ' ' . $workSchedule->check_out);
                            $checkOutEarly = $coreTimeOut - $actualCheckOut;
                            $actualCheckOut = $workScheduleOut - $checkOutEarly;
                            $checkOutWithTimeBlock = $this->ceilByTimestamp($workScheduleOut, $actualCheckOut, $timesheetMinTimeBlock * 60);
                            // Set missing hours of check-out in the core time
                            $this->missing_hours_in_core_time += ($workScheduleOut - $checkOutWithTimeBlock) / 3600;
                            $this->periodMissingCoretime['check_out'] = Period::make(
                                $startMissingCheckOut,
                                $coreTimeCheckOutString,
                                Precision::SECOND
                            );
                        }
                    }
                }
                break;
            default:
                break;
        }

        if ($workSchedule->is_off_day) {
            if ($this->check_in && !isset($checkIn)) {
                $checkIn = Carbon::parse($this->log_date . ' ' . $this->check_in);
                if ($this->start_next_day) {
                    $checkIn->addDay();
                }
            }
            if ($this->check_out && !isset($checkOut)) {
                $checkOut = Carbon::parse($this->log_date . ' ' . $this->check_out);
                if ($this->next_day) {
                    $checkOut->addDay();
                }
            }
        } else {
            if ($this->check_in && $workSchedule->check_in && !isset($checkIn)) {
                $checkIn = $this->checkInByActualTime($workSchedule->check_in, $timesheetMinTimeBlock);
            }

            if ($this->check_out && $workSchedule->check_out && !isset($checkOut)) {
                $checkOut = $this->checkOutByActualTime($workSchedule->check_out, $timesheetMinTimeBlock, $workSchedule->next_day ?? null);
            }
        }


        if (!isset($checkIn) || !isset($checkOut) || $checkIn->isAfter($checkOut)) {
            // Nếu vẫn thiếu giờ, fallback về giờ dummy (không đi làm)
            $checkIn = Carbon::parse($this->log_date . ' 00:00:00');
            $checkOut = Carbon::parse($this->log_date . ' 00:00:01');
        }

        return [$checkIn, $checkOut];
    }

    function roundCeilToNearestBlock($time, $block)
    {
        $dateTime = Carbon::createFromFormat('H:i', $time);
        $roundedMinutes = ceil($dateTime->minute / $block) * $block;
        $roundedTime = $dateTime->setMinute($roundedMinutes);

        return $roundedTime->format('H:i');
    }

    /**
     * @param $start_time
     * @param $timesheetMinTimeBlock int
     *
     * @return Carbon
     */
    private function checkInByActualTime($start_time, int $timesheetMinTimeBlock): Carbon
    {
        $startIn = strtotime($this->log_date . ' ' . $start_time);
        $actualCheckIn = $this->start_next_day ? strtotime($this->log_date . ' ' . $this->check_in . '+1 day') : strtotime($this->log_date . ' ' . $this->check_in);
        $checkInWithTimeBlock = $this->floorByTimestamp($startIn, $actualCheckIn, $timesheetMinTimeBlock * 60);
        return Carbon::createFromTimestamp($checkInWithTimeBlock);
    }

    /**
     * @param $end_time
     * @param $next_day
     * @param $timesheetMinTimeBlock int
     *
     * @return Carbon
     */
    private function checkOutByActualTime($end_time, int $timesheetMinTimeBlock, $next_day): Carbon
    {
        $endOut = $next_day ? strtotime($this->log_date . ' ' . $end_time . '+1 day') : strtotime($this->log_date . ' ' . $end_time);
        $actualCheckOut = $this->next_day ? strtotime($this->log_date . ' ' . $this->check_out . '+1 day') : strtotime($this->log_date . ' ' . $this->check_out);
        $checkOutWithTimeBlock = $this->ceilByTimestamp($endOut, $actualCheckOut, $timesheetMinTimeBlock * 60);
        return Carbon::createFromTimestamp($checkOutWithTimeBlock);
    }

    private function floorByTimestamp($p1, $p2, $compare_value)
    {
        $floor = floor(($p1 - $p2) / $compare_value);
        return $p1 - ($floor * $compare_value);
    }

    private function ceilByTimestamp($p1, $p2, $compare_value)
    {
        $ceil = ceil(($p1 - $p2) / $compare_value);
        return $p1 - ($ceil * $compare_value);
    }

    /**
     * Re-calculate OT on this day by summing worktime register timesheet.
     *
     * @return void
     */
    public function reCalculateOT()
    {
        $reCalwrts = WorkTimeRegisterTimesheet::selectRaw("SUM(time_values) as hours, type, register_id")
            ->where('timesheet_id', $this->id)
            ->with('workTimeRegister')
            ->groupBy('type')->get();
        if (empty($reCalwrts) || !($reCalwrts->count() > 0)) {
            return;
        }

        $this->overtime_hours = 0;
        $this->midnight_overtime_hours = 0;
        $this->makeup_hours = 0;
        $this->manual_makeup_hours = 0;
        foreach ($reCalwrts as $wrt) {
            $this->{WorkTimeRegisterTimesheet::TYPE_TEXT[$wrt->type]} += $wrt->hours;

            $wtr = $wrt->workTimeRegister;
            // Update column regarding with compensatory
            if (!empty($wtr) && $wrt->type == 3) {
                if (isset($wtr->auto_created) && !$wtr->auto_created) {
                    $this->manual_makeup_hours += $wrt->hours;
                }
            }
        }
    }

    private function validateWorkTimeRegisterTimesheetByCheckInOut($dayPeriod, $otMinTimeBlock, $flexibleTimesheetSetting, $dayBeginMark)
    {
        $wrts = WorkTimeRegisterTimesheet::with('workTimeRegister')->where('timesheet_id', $this->id)->get();

        $midnightOTPeriods = PeriodHelper::getNightPeriodsForDay($this->log_date, $dayBeginMark);

        foreach ($wrts as $wrt) {
            $workTimeRegister = $wrt->workTimeRegister;
            if(empty($workTimeRegister)){
                continue;
            }
            $overTimeHours = 0;
            $midnightOvertimeHours = 0;
            $periods = $workTimeRegister->periods;
            foreach ($periods as $period) {
                [$checkIn, $checkOut] = $this->getCheckInOutCarbonByRequestPeriod($period, $this->clientEmployee, $otMinTimeBlock, $flexibleTimesheetSetting);
                $timesheetPeriod = Period::make($checkIn, $checkOut, Precision::SECOND);

                $startTimeRequest = Carbon::parse($period->start_datetime);
                $endTimeRequest = Carbon::parse($period->end_datetime);

                if ($startTimeRequest->isAfter($endTimeRequest)) {
                    $OTStartTime = $endTimeRequest;
                    $OTEndTime = $startTimeRequest;
                } else {
                    $OTStartTime = $startTimeRequest;
                    $OTEndTime = $endTimeRequest;
                }

                $otPeriods = Period::make($OTStartTime, $OTEndTime, Precision::SECOND);
                $otPeriods = $otPeriods->overlapSingle($dayPeriod);

                if (!($otPeriods instanceof Period)) {
                    continue;
                }

                $otBreakPeriod = Period::make($period->start_break_datetime, $period->end_break_datetime, Precision::SECOND, Boundaries::EXCLUDE_ALL);
                $otPeriods = $otPeriods->diffSingle($otBreakPeriod);

                $otTimeMinuteTemp = 0;
                $otMidnightHourTemp = 0;

                if (!$otPeriods || $otPeriods->isEmpty()) continue;
                foreach ($otPeriods as $otPeriod) {
                    //If this request isn't skip logic => need to overlap with actual checkin/checkout
                    if (!$workTimeRegister->skip_logic) {
                        $otPeriod = $timesheetPeriod->overlapSingle($otPeriod);
                        if (!$otPeriod) continue;
                    }
                    $otTimeMinuteTemp += PeriodHelper::countMinutes($otPeriod);

                    $overlapsMidnight = (new PeriodCollection($otPeriod))->overlap($midnightOTPeriods);
                    foreach ($overlapsMidnight as $overlapPeriod) {
                        $otMidnightHourTemp += PeriodHelper::countHours($overlapPeriod);
                    }
                }

                $otTimeHourTemp = round($otMinTimeBlock * floor($otTimeMinuteTemp / $otMinTimeBlock) / 60,
                    2, PHP_ROUND_HALF_DOWN);
                $overTimeHours += $otTimeHourTemp;

                if ($otMidnightHourTemp > $overTimeHours) {
                    $midnightOvertimeHours += $overTimeHours;
                } else {
                    $midnightOvertimeHours += $otMidnightHourTemp;
                }

                // 2024-05-14: Update period with overtime hours
                // Used in timesheet grid display
                $period->so_gio_tam_tinh = $otTimeHourTemp;
                $period->save();
            }

            if ($wrt->type == WorkTimeRegisterTimesheet::OT_TYPE) {
                $wrt->time_values = $overTimeHours;
            } else if ($wrt->type == WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE) {
                $wrt->time_values = $midnightOvertimeHours;
            } else if ($wrt->type == WorkTimeRegisterTimesheet::OT_MAKEUP_HOURS_TYPE) {
                $wrt->time_values = $overTimeHours;
            }
            $wrt->save();
        }
    }

    /**
     * This function will get and set checkin/checkout for shifts based on current time ($check_time)
     *
     * @param $check_time Carbon
     * @param $source String
     *
     * @return void
     */
    public function checkTimeWithMultiShift(Carbon $check_time = null, string $source = "App")
    {
        $now = ($check_time ?? Carbon::now(Constant::TIMESHEET_TIMEZONE))->setSecond(0);
        $this->storeInOut($now);

        /**
         * Checkin/checkout for flexible shifts
         */
        $flexibleShiftMapping = $this->getFlexibleShift($now);
        if ($flexibleShiftMapping instanceof TimesheetShiftMapping) {
            $this->checkFlexibleMultipleShift($now, $flexibleShiftMapping);
        }

        /**
         * Checkin flow for normal shifts
         */
        $checkInShiftMapping = $this->getShiftToCheckIn($now, $source);
        if ($checkInShiftMapping instanceof TimesheetShiftMapping) {
            $checked_in = $this->checkInMultipleShift($now, $checkInShiftMapping);
        }

        /**
         * Checkout flow for normal shifts
         */
        $checkOutShiftMapping = $this->getShiftToCheckOut($now, $source);
        if (
            $checkOutShiftMapping instanceof TimesheetShiftMapping
            && (empty($checked_in) || $checkInShiftMapping->id != $checkOutShiftMapping->id)
        ) {
            $this->checkOutMultipleShift($now, $checkOutShiftMapping, $checkInShiftMapping);
        }

    }

    public function storeInOut(Carbon $now)
    {
        $checkIn = Carbon::parse($this->log_date . ' ' . $this->check_in, Constant::TIMESHEET_TIMEZONE);
        if (!$this->check_in || $checkIn->isAfter($now)) {
            $this->check_in = $now->format('H:i');
        }

        $checkOut = Carbon::parse($this->log_date . ' ' . $this->check_out);
        if ($this->next_day) {
            $checkOut->addDay();
        }
        if (!$this->check_out || $checkOut->isBefore($now)) {
            $this->check_out = $now->format('H:i');
            if (!$now->isSameDay($this->log_date)) {
                $this->next_day = 1;
            } else {
                $this->next_day = 0;
            }
        }
    }

    /**
     *
     * @param $now Carbon
     * @param $flexibleShiftMapping TimesheetShiftMapping
     *
     */
    private function checkFlexibleMultipleShift(Carbon $now, TimesheetShiftMapping $flexibleShiftMapping)
    {
        if (!$flexibleShiftMapping->check_in || $now->isBefore($flexibleShiftMapping->check_in)) {
            $flexibleShiftMapping->check_in = $now->toDateTimeString();
        } elseif (!$flexibleShiftMapping->check_out || $now->isAfter($flexibleShiftMapping->check_out)) {
            $flexibleShiftMapping->check_out = $now->toDateTimeString();
        }
        $flexibleShiftMapping->save();
    }

    /**
     *
     * @param $now Carbon
     * @param $checkInShiftMapping TimesheetShiftMapping
     *
     * @return boolean
     */
    private function checkInMultipleShift(Carbon $now, TimesheetShiftMapping $checkInShiftMapping)
    {
        if (
            !$checkInShiftMapping->check_in
            || $now->isBefore(Carbon::parse($checkInShiftMapping->check_in, Constant::TIMESHEET_TIMEZONE))
        ) {
            /** We cannot check in when current time is out of the acceptable checkin period of shift */
            if (!empty($checkInShiftMapping->timesheetShift->acceptable_check_in)) {
                $acceptableCheckIn = Carbon::parse($this->log_date . ' ' . $checkInShiftMapping->timesheetShift->acceptable_check_in, Constant::TIMESHEET_TIMEZONE)->setSecond(0);
                if ($now->greaterThanOrEqualTo($acceptableCheckIn)) {
                    $checkInShiftMapping->check_in = $now->toDateTimeString();
                    $checkInShiftMapping->save();
                    return true;
                }
            } else {
                $checkInShiftMapping->check_in = $now->toDateTimeString();
                $checkInShiftMapping->save();
                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param $now Carbon
     * @param $checkOutShiftMapping TimesheetShiftMapping
     * @param $checkInShiftMapping TimesheetShiftMapping | null
     *
     */
    private function checkOutMultipleShift(Carbon $now, TimesheetShiftMapping $checkOutShiftMapping, TimesheetShiftMapping $checkInShiftMapping = null)
    {
        if (
            !$checkOutShiftMapping->check_out
            || $now->isAfter(Carbon::parse($checkOutShiftMapping->check_out, Constant::TIMESHEET_TIMEZONE))
        ) {
            /**
             * We cannot check out when current time is within the acceptable checkin period of the next shift
             */
            if (
                !empty($checkInShiftMapping) &&
                !empty($checkInShiftMapping->timesheetShift->acceptable_check_in)
            ) {
                $acceptableCheckIn = Carbon::parse($this->log_date . ' ' . $checkInShiftMapping->timesheetShift->acceptable_check_in, Constant::TIMESHEET_TIMEZONE)->setSecond(0);
                $shiftCheckOut = Carbon::parse($this->log_date . ' ' . $checkOutShiftMapping->shift_check_out, Constant::TIMESHEET_TIMEZONE);
                if ($checkOutShiftMapping->shift_next_day) {
                    $shiftCheckOut->addDay();
                }

                if ($now->isBefore($acceptableCheckIn) || $checkInShiftMapping->id == $checkOutShiftMapping->id) {
                    $checkOutShiftMapping->check_out = $now->toDateTimeString();
                }
            } else {
                $checkOutShiftMapping->check_out = $now->toDateTimeString();
            }

            $checkOutShiftMapping->autoCheckInCheckOutForAdjacentShift($this->timesheetShiftMapping, $this->log_date);
            $checkOutShiftMapping->save();
        }
    }

    /**
     *
     * @param $now Carbon
     *
     * @return TimesheetShiftMapping | null
     */
    private function getFlexibleShift(Carbon $now)
    {
        foreach ($this->timesheetShiftMapping as $item) {
            if ($item->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT) {
                $schedule_in = Carbon::parse($this->log_date . ' ' . $item->timesheetShift->check_in, Constant::TIMESHEET_TIMEZONE);
                $schedule_out = Carbon::parse($this->log_date . ' ' . $item->timesheetShift->check_out, Constant::TIMESHEET_TIMEZONE);
                if ($item->timesheetShift->next_day) {
                    $schedule_out->addDay();
                }

                if ($now->isBetween($schedule_in, $schedule_out)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     *
     * @param $now Carbon
     *
     * @return TimesheetShiftMapping | null
     */

    private function getShiftToCheckIn(Carbon $now, $source = "App")
    {
        $shiftMapping = null;

        /** Get shifts which have check_out is after now
         * After that, we get shift which has check_in is smallest.*/
        foreach ($this->timesheetShiftMapping as $item) {
            if ($item->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT) {
                continue;
            }

            $checkOut = Carbon::parse($this->log_date . ' ' . $item->shift_check_out, Constant::TIMESHEET_TIMEZONE);
            if ($item->shift_next_day) {
                $checkOut->addDay();
            }

            if ($checkOut && $checkOut->isAfter($now)) {
                // checkSkipHanet
                $checkSkipHanet = HanetHelper::checkSkipHanet($item->timesheetShift->client_id, $item->skip_hanet, $source, $item->id, TimesheetShiftMapping::class);

                if ($checkSkipHanet) {
                    logger(__METHOD__ . ": skipHanet [{$item->skip_hanet}] -- source: {$source} -- clientEmployeeID {$item->timesheet->client_employee_id} -- timesheetShiftMappingID: {$item->id} -- logDate: {$item->timesheet->log_date}");
                    return null;
                }

                $checkIn = Carbon::parse($this->log_date . ' ' . $item->shift_check_in, Constant::TIMESHEET_TIMEZONE);
                if (!$shiftMapping || Carbon::parse($this->log_date . ' ' . $shiftMapping->shift_check_in, Constant::TIMESHEET_TIMEZONE)->isAfter($checkIn)) {
                    $shiftMapping = $item;
                }
            }
        }
        return $shiftMapping;
    }

    /**
     *
     * @param $now Carbon
     *
     * @return TimesheetShiftMapping | null
     */
    private function getShiftToCheckOut(Carbon $now, $source = "App")
    {
        $shiftMapping = null;

        /** Get shifts which have check_in or acceptable_check_in is before now
         * After that, we get shift which has check_out is biggest.*/
        foreach ($this->timesheetShiftMapping as $item) {
            if ($item->timesheetShift->shift_type == TimesheetShift::FLEXIBLE_SHIFT) {
                continue;
            }

            if (empty($item->timesheetShift->acceptable_check_in)) {
                $itemCheckIn = Carbon::parse($this->log_date . ' ' . $item->shift_check_in, Constant::TIMESHEET_TIMEZONE);

            } else {
                $itemCheckIn = Carbon::parse($this->log_date . ' ' . $item->timesheetShift->acceptable_check_in, Constant::TIMESHEET_TIMEZONE)->setSecond(0);
            }

            if ($itemCheckIn && $itemCheckIn->lessThanOrEqualTo($now)) {

                // checkSkipHanet
                $checkSkipHanet = HanetHelper::checkSkipHanet($item->timesheetShift->client_id, $item->skip_hanet, $source, $item->id, TimesheetShiftMapping::class);

                if ($checkSkipHanet) {
                    logger(__METHOD__ . ": skipHanet [{$item->skip_hanet}] -- source: {$source} -- clientEmployeeID {$item->timesheet->client_employee_id} -- timesheetShiftMappingID: {$item->id} -- logDate: {$item->timesheet->log_date}");
                    return null;
                }

                $itemCheckOut = Carbon::parse($this->log_date . ' ' . $item->shift_check_out, Constant::TIMESHEET_TIMEZONE);
                if ($item->shift_next_day) {
                    $itemCheckOut->addDay();
                }
                if (!$shiftMapping) {
                    $shiftMapping = $item;
                } else {
                    $currentCheckOut = Carbon::parse($this->log_date . ' ' . $shiftMapping->shift_check_out, Constant::TIMESHEET_TIMEZONE);
                    if ($shiftMapping->shift_next_day) {
                        $currentCheckOut->addDay();
                    }
                    if ($currentCheckOut->isBefore($itemCheckOut)) {
                        $shiftMapping = $item;
                    }
                }
            }
        }
        return $shiftMapping;
    }

    /**
     * @param $clientWorkflowSetting ClientWorkflowSetting|null
     */
    public function calculateMultiTimesheet($clientWorkflowSetting = null)
    {
        if (!$clientWorkflowSetting) {
            $clientWorkflowSetting = ClientWorkflowSetting::where('client_id', $this->clientEmployee->client_id)->first();
            if (!$clientWorkflowSetting) return;
        }
        $client = Client::select('id', 'timesheet_min_time_block', 'ot_min_time_block')->where('id', $this->clientEmployee->client_id)->first();
        $otMinTimeBlock = $client->ot_min_time_block ?: 1;
        $timesheetMinTimeBlock = $client->timesheet_min_time_block ?: 1;

        $this->mission_hours = 0;
        $this->mission_road_hours = 0;
        $this->mission_airline_hours = 0;
        $this->wfh_hours = 0;
        $this->outside_working_hours = 0;
        $this->other_business_hours = 0;
        $this->working_hours = 0;
        $this->shift = 0;
        $this->rest_hours = 0;
        $this->paid_leave_hours = 0;
        $this->unpaid_leave_hours = 0;
        $this->overtime_hours = 0;
        $this->midnight_overtime_hours = 0;
        $this->shift_enabled = 0;
        $this->dayBeginMark = $clientWorkflowSetting->getTimesheetDayBeginAttribute();

        $dayStart = Carbon::parse($this->log_date . ' ' . $this->dayBeginMark);
        $dayEnd = $dayStart->clone()->addDay();
        $dayPeriod = PeriodHelper::makePeriod($dayStart, $dayEnd);

        $midnightOTPeriods = PeriodHelper::getNightPeriodsForDay($this->log_date, $this->dayBeginMark);

        $overlapMethod = function ($carry, $period) use ($timesheetMinTimeBlock) {
            $minutes = floor((PeriodHelper::countMinutes($period)) / $timesheetMinTimeBlock) * $timesheetMinTimeBlock;
            $carry += round($minutes / 60, 4);
            return $carry;
        };

        $schedulePeriods = new PeriodCollection();
        $convertedInOutPeriods = new PeriodCollection();
        $unPaidLeavePeriods = new PeriodCollection();
        $paidLeavePeriods = new PeriodCollection();
        $missionPeriods = new PeriodCollection();
        $missionRoadPeriods = new PeriodCollection();
        $missionAirlinePeriods = new PeriodCollection();
        $wfhPeriods = new PeriodCollection();
        $outsidePeriods = new PeriodCollection();
        $specialOtherPeriods = new PeriodCollection();
        /** Get work schedule period and working period */
        foreach ($this->timesheetShiftMapping as $item) {
            $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
            $schedulePeriods = PeriodHelper::merge2Collections($schedulePeriods, $schedulePeriodsWithoutRest);
            /** Work schedule is only allowed in the day period */
            $schedulePeriods = $schedulePeriods->overlapSingle(new PeriodCollection($dayPeriod));

            $convertedInOutPeriodsWithoutRest = PeriodHelper::subtract($item->converted_in_out_period, $item->rest_shift_period);
            $convertedInOutPeriods = PeriodHelper::merge2Collections($convertedInOutPeriods, $convertedInOutPeriodsWithoutRest);
        }

        /** Get request periods */
        foreach ($this->workTimeRegister as $item) {
            /** @var WorkTimeRegister $item */
            switch ($item->type) {
                case "leave_request":
                    if ($item->sub_type == "unauthorized_leave") {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $unPaidLeavePeriods = PeriodHelper::merge2Collections($unPaidLeavePeriods, $result);
                    } else {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $paidLeavePeriods = PeriodHelper::merge2Collections($paidLeavePeriods, $result);
                    }
                    break;
                case "congtac_request":
                    if ($item->sub_type == "business_trip") {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $missionPeriods = PeriodHelper::merge2Collections($missionPeriods, $result);
                        if ($item->category == 'road') {
                            $resultRoadBusiness = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                            $missionRoadPeriods = PeriodHelper::merge2Collections($missionRoadPeriods, $resultRoadBusiness);
                        } elseif ($item->category == 'airline') {
                            $resultAirlineBusiness = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                            $missionAirlinePeriods = PeriodHelper::merge2Collections($missionAirlinePeriods, $resultAirlineBusiness);
                        }
                    } elseif ($item->sub_type == "outside_working") {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $outsidePeriods = PeriodHelper::merge2Collections($outsidePeriods, $result);
                    } elseif ($item->sub_type == "wfh") {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $wfhPeriods = PeriodHelper::merge2Collections($wfhPeriods, $result);
                    } else {
                        $result = $this->getRequestPeriodsOverlapSchedule($item->periods, $dayPeriod, $schedulePeriods);
                        $specialOtherPeriods = PeriodHelper::merge2Collections($specialOtherPeriods, $result);
                    }
                    break;
                case "overtime_request":
                    [$overTimeHours, $midnightOvertimeHours] = $item->getOverTimeFromMultipleShift($schedulePeriods, $midnightOTPeriods, $convertedInOutPeriods, $dayPeriod, (int)$otMinTimeBlock);

                    WorkTimeRegisterTimesheet::updateOrCreate([
                        'register_id' => $item->id,
                        'timesheet_id' => $this->id,
                        'type' => WorkTimeRegisterTimesheet::OT_TYPE,
                    ], [
                        'register_id' => $item->id,
                        'timesheet_id' => $this->id,
                        'client_employee_id' => $this->client_employee_id,
                        'type' => WorkTimeRegisterTimesheet::OT_TYPE,
                        'time_values' => $overTimeHours
                    ]);

                    WorkTimeRegisterTimesheet::updateOrCreate([
                        'register_id' => $item->id,
                        'timesheet_id' => $this->id,
                        'type' => WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE,
                    ], [
                        'register_id' => $item->id,
                        'timesheet_id' => $this->id,
                        'client_employee_id' => $this->client_employee_id,
                        'type' => WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE,
                        'time_values' => $midnightOvertimeHours
                    ]);

                    break;
                default:
                    break;
            }
        }

        /** Get requests hours */
        $this->unpaid_leave_hours = $unPaidLeavePeriods->reduce($overlapMethod, 0);
        $this->paid_leave_hours = $paidLeavePeriods->reduce($overlapMethod, 0);
        $this->mission_hours = $missionPeriods->reduce($overlapMethod, 0);
        $this->mission_road_hours = $missionRoadPeriods->reduce($overlapMethod, 0);
        $this->mission_airline_hours = $missionAirlinePeriods->reduce($overlapMethod, 0);
        $this->wfh_hours = $wfhPeriods->reduce($overlapMethod, 0);
        $this->outside_working_hours = $outsidePeriods->reduce($overlapMethod, 0);
        $this->other_business_hours = $specialOtherPeriods->reduce($overlapMethod, 0);
        $this->working_hours += ($this->wfh_hours + $this->mission_hours + $this->outside_working_hours + $this->other_business_hours);
        if ($clientWorkflowSetting->enable_overtime_request) {
            $this->reCalculateOT();
        }

        /** Get working hours by subtracting working period with request periods */
        foreach ($this->timesheetShiftMapping as &$item) {
            /** @var TimesheetShiftMapping $item */
            $workingPeriods = $item->block_in_out_period->overlapSingle($item->schedule_shift_period);
            /**
             * Calculate working_hour
             */
            if (!$workingPeriods) {
                $item->rest_hours = 0;
                $item->working_hours = 0;
            } else {
                $workingPeriods = PeriodHelper::subtract($workingPeriods, $item->rest_shift_period);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $unPaidLeavePeriods);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $paidLeavePeriods);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $missionPeriods);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $wfhPeriods);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $outsidePeriods);
                $workingPeriods = PeriodHelper::subtractPeriodCollection($workingPeriods, $specialOtherPeriods);

                $workingMinutes = $workingPeriods->reduce(function ($carry, $period) {
                    $carry += PeriodHelper::countMinutes($period);
                    return $carry;
                }, 0);

                $workingHours = (floor($workingMinutes / $timesheetMinTimeBlock) * $timesheetMinTimeBlock) / 60;

                $item->rest_hours = round((PeriodHelper::countMinutes($item->rest_shift_period)) / 60, 2);
                $item->working_hours = $workingHours;

                $this->rest_hours += $item->rest_hours;
                /** If this day is holiday
                 * The working time become to the overtime.*/
                if ($this->is_holiday) {
                    $this->overtime_hours += $workingHours;
                    $midnightOverTimePeriods = $workingPeriods->overlapSingle($midnightOTPeriods);
                    $this->midnight_overtime_hours += $midnightOverTimePeriods->reduce($overlapMethod, 0);
                } else {
                    $this->working_hours += $workingHours;
                }
            }

            /**
             * Calculate shift
             */
            if ($item->timesheetShift->shift) {

                $overlapPeriodCollect = $item->schedule_shift_without_rest;
                $overlapPeriodCollect = PeriodHelper::subtractPeriodCollection($overlapPeriodCollect, $paidLeavePeriods);
                $overlapPeriodCollect = PeriodHelper::subtractPeriodCollection($overlapPeriodCollect, $missionPeriods);
                $overlapPeriodCollect = PeriodHelper::subtractPeriodCollection($overlapPeriodCollect, $wfhPeriods);
                $overlapPeriodCollect = PeriodHelper::subtractPeriodCollection($overlapPeriodCollect, $outsidePeriods);
                $overlapPeriodCollect = PeriodHelper::subtractPeriodCollection($overlapPeriodCollect, $specialOtherPeriods);

                if (PeriodHelper::contains($item->block_in_out_period, $overlapPeriodCollect)) {
                    $item->shift = $item->timesheetShift->shift;
                    $this->shift += $item->timesheetShift->shift;
                } else {
                    $item->shift = 0;
                }

            } else {
                $item->shift = 0;
            }


            $item->save();
        }
    }

    /**
     * Getting request periods by overlapping request periods with the schedule period.
     *
     * @param Collection $request
     * @param Period $dayPeriod
     * @param PeriodCollection $schedulePeriods
     *
     * @return PeriodCollection
     */
    public function getRequestPeriodsOverlapSchedule(
        Collection       $request,
        Period           $dayPeriod,
        PeriodCollection $schedulePeriods
    ): PeriodCollection
    {
        $returnPeriods = new PeriodCollection();
        foreach ($request as $period) {
            /** @var WorkTimeRegisterPeriod $period */
            if ($period->type_register == WorkTimeRegisterPeriod::TYPE_ALL_DAY) {
                if ($period->date_time_register != $this->log_date) {
                    // Request is not for this day, lets skip
                    continue;
                }
                // nghỉ cả ngày, tức = $wsPeriod
                $returnPeriods = PeriodHelper::merge2Collections($returnPeriods, $schedulePeriods);
            } else {
                $requestPeriod = $period->getPeriod()->overlapSingle($dayPeriod);
                if (!$requestPeriod) {
                    continue;
                }
                $breakPeriod = Period::make($period->start_break_datetime, $period->end_break_datetime, Precision::SECOND, Boundaries::EXCLUDE_ALL);

                $requestPeriod = $requestPeriod->diffSingle($breakPeriod);

                $mergePeriod = $requestPeriod->overlap($schedulePeriods);

                $returnPeriods = PeriodHelper::merge2Collections($returnPeriods, $mergePeriod);
            }
        }
        return $returnPeriods;
    }

    private function overrideMissingCoreTime($joinPeriods, $restPeriod)
    {
        if (!empty($this->periodMissingCoretime['check_in'])) {
            // validate break time and leave, bussiness, leave application
            $missingCheckInWithCoreTime = call_user_func_array([$this->periodMissingCoretime['check_in'], 'overlap'], $joinPeriods->toArray());
            $overlapWithRestMinute = PeriodHelper::countMinutes($restPeriod->overlapSingle($this->periodMissingCoretime['check_in']));
            $overlapWithRestHours = 0;
            if ($overlapWithRestMinute > 0) {
                $overlapWithRestHours = $overlapWithRestMinute / 60;
            }
            $missingCoreTimeHours = $missingCheckInWithCoreTime->reduce(function ($carry, $period) use ($restPeriod) {
                $overlapWithRestMinute = $period->overlapSingle($restPeriod);
                $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRestMinute)) / 60,
                    4
                );
                return $carry;
            }, 0);
            $this->missing_hours_in_core_time -= ($missingCoreTimeHours + $overlapWithRestHours);
        }

        if (!empty($this->periodMissingCoretime['check_out'])) {
            // validate break time and leave, bussiness, leave application
            $missingCheckOutWithCoreTime = call_user_func_array([$this->periodMissingCoretime['check_out'], 'overlap'], $joinPeriods->toArray());
            $overlapWithRestMinute = PeriodHelper::countMinutes($restPeriod->overlapSingle($this->periodMissingCoretime['check_out']));
            $overlapWithRestHours = 0;
            if ($overlapWithRestMinute > 0) {
                $overlapWithRestHours = $overlapWithRestMinute / 60;
            }
            $missingCoreTimeHours = $missingCheckOutWithCoreTime->reduce(function ($carry, $period) use ($restPeriod) {
                $overlapWithRestMinute = $period->overlapSingle($restPeriod);
                $carry += round((PeriodHelper::countMinutes($period) - PeriodHelper::countMinutes($overlapWithRestMinute)) / 60,
                    4
                );
                return $carry;
            }, 0);
            $this->missing_hours_in_core_time -= ($missingCoreTimeHours + $overlapWithRestHours);
        }
    }

    public function resetInOutMultiShift()
    {
        foreach($this->timesheetShiftMapping as $item) {
            $item->check_in = $item->check_out = null;
        }
    }
}
