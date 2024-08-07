<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\WorkScheduleGroup;
use App\Support\Constant;
use App\Support\PeriodHelper;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;
use Znck\Eloquent\Traits\BelongsToThrough;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_id
 * @property string $user_id
 * @property string $assigned
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property string $message
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property User   $user
 * @property Client $client
 */
class WorktimeRegister extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use Concerns\UsesUuid, SoftDeletes, HasAssignment, BelongsToThrough, LogsActivity, HasFactory;

    /**
     * @var array|string[]
     */
    protected static array $logAttributes = ['*'];

    protected $table = 'work_time_registers';

    public $timestamps = true;
    protected $dates = ['deleted_at'];

    protected $realStatus = false;
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
    }

    /**
     * @var array
     */
    protected $fillable = [
        'client_employee_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'type',
        'sub_type',
        'reason',
        'status',
        'approved_by',
        'approved_date',
        'approved_comment',
        'deleted_at',
        'created_at',
        'category',
        'skip_logic',
        'creator_id',
        'group_id',
        'info_app'
    ];



    public function getTotalDurationAttribute()
    {
        return collect($this->workTimeRegisterPeriod)->sum('duration');
    }

    public function getMediaModel()
    {
        return $this->getMedia('WorktimeRegister');
    }

    public function workTimeRegisterPeriod(): Hasmany
    {
        return $this->hasMany(WorkTimeRegisterPeriod::class);
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'client_employee_id');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployeeManager()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'creator_id', 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'approved_by');
    }

    public function approves(): MorphMany
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function client(): \Znck\Eloquent\Relations\BelongsToThrough
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(WorkTimeRegisterPeriod::class);
    }

    public function workTimeRegisterTimesheets(): HasMany
    {
        return $this->hasMany(WorkTimeRegisterTimesheet::class, 'register_id');
    }

    public function scopeIsApproved($query)
    {
        return $query->whereStatus('approved');
    }

    public function paymentRequest()
    {
        return $this->hasMany(PaymentRequest::class, 'business_trip_id', 'id');
    }

    public function worktimeRegisterCategory()
    {
        return $this->hasOne(WorktimeRegisterCategory::class, 'id', 'category');
    }

    public function getApproveDeadlineAtAttribute()
    {
        $employee = ClientEmployee::find($this->client_employee_id);
        if ($employee && $employee->work_schedule_group_template_id) {
            $periods = $this->workTimeRegisterPeriod;
            $argsData = [];
            foreach ($periods as $period) {
                $query = WorkScheduleGroup::where('work_schedule_group_template_id', $employee->work_schedule_group_template_id)
                    ->whereDate('timesheet_from', '<=', $period->date_time_register)
                    ->whereDate('timesheet_to', '>=', $period->date_time_register)->first();
                if ($query && $query->approve_deadline_at) {
                    $argsData[$period->date_time_register] = $query->approve_deadline_at;
                }
            }
            return json_encode($argsData);
        }
        return null;
    }

    public function getCheckWorkScheduleAttribute()
    {
        $employee = ClientEmployee::find($this->client_employee_id);
        $wsgtId = $employee->work_schedule_group_template_id;
        if ($employee && $employee->work_schedule_group_template_id) {
            $periods = $this->workTimeRegisterPeriod;
            $argsData = [];
            foreach ($periods as $period) {
                $ws = WorkSchedule::query()
                    ->whereHas('workScheduleGroup', function ($q) use ($wsgtId) {
                        $q->where('work_schedule_group_template_id', $wsgtId);
                    })
                    ->where('schedule_date', $period->date_time_register)
                    ->first();
                if ($ws) {
                    $argsData[$period->date_time_register] = true;
                } else {
                    $argsData[$period->date_time_register] = false;
                }
            }
            return json_encode($argsData);
        }
        return null;
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10);
    }

    public function getRegisteredHoursAttribute(): float
    {
        $diffHours = 0;
        $periods = $this->workTimeRegisterPeriod;
        foreach ($periods as $key => $period) {
            $diffHours += $period->duration;
        }
        return round($diffHours, 2);
    }

    public function getParamTotalAndRemainHoursAttribute(): ?string
    {
        $employee = $this->clientEmployee;
        if (!$employee) {
            return null;
        }

        $date = Carbon::parse($this->start_time)->toDateString();

        if ($this->type == 'overtime_request') {
            return $this->calculateOvertimeHours($employee, $date);
        }else if ($this->type == 'leave_request') {
            return $this->calculateLeaveHours($employee, $date);
        }

        return null;
    }

    public function getRemainHoursOfApprovePageAttribute(): ?string
    {
        $employee = $this->clientEmployee;
        if (!$employee) {
            return null;
        }

        $date = Carbon::parse($this->start_time)->toDateString();

        if ($this->type == 'overtime_request') {
            return $this->calculateOvertimeHours($employee, $date);
        }else if ($this->type == 'leave_request') {
            return $this->calculateLeaveHours($employee, $date, true);
        }

        return null;
    }

    private function calculateOvertimeHours($employee, $date)
    {
        $arrayParamTotalAndRemain = null;
        // Get client setting OT
        $clientSettingOt = OvertimeCategory::where([
            ['client_id', $employee->client_id],
            ['start_date', '<=', $date],
            ['end_date', '>=', $date],
        ])->first();
        if($clientSettingOt) {
            $ws = $this->getWorkScheduleGroup(['date' => $date, 'work_schedule_group_template_id' => $employee->work_schedule_group_template_id]);
            if(!$ws) {
                return null;
            }
            $timesheets = Timesheet::select(['overtime_hours', 'log_date'])
                ->where('log_date', '>=', $clientSettingOt->start_date)
                ->where('log_date', '<=', $ws->timesheet_to)
                ->where('client_employee_id', $this->client_employee_id)
                ->get();

            $startDate = Carbon::parse($ws->timesheet_from)->toDateString();
            $endDate = Carbon::parse($ws->timesheet_to)->toDateString();
            $numberHourOtFilterByYear = $timesheets->sum('overtime_hours');
            $totalOvertimeByMonth = $timesheets->whereBetween('log_date',[$startDate,$endDate])->sum('overtime_hours');


            // The number of overtime hours filtered by the filter is accumulated by each month of the year
            $arrayParamTotalAndRemain['total_overtime_by_month'] = $totalOvertimeByMonth;
            $arrayParamTotalAndRemain['remain_overtime_by_month'] = round(max($clientSettingOt->entitlement_month - $totalOvertimeByMonth, 0), 2);
            $arrayParamTotalAndRemain['remain_overtime_by_year'] = round(max( $clientSettingOt->entitlement_year - $numberHourOtFilterByYear, 0),2);
            $arrayParamTotalAndRemain = json_encode($arrayParamTotalAndRemain);
        }

        return $arrayParamTotalAndRemain;
    }

    private function calculateLeaveHours($employee, $date, $approvePage = false)
    {
        $arrayParamTotalAndRemain = null;
        // Get client setting Leave Time
        $clientEmployeeLeaveManagement = ClientEmployeeLeaveManagement::whereHas('leaveCategory', function ($query) use ($employee, $date) {
            $query->where([
                ['client_id', $employee->client_id],
                ['type', $this->sub_type],
                ['sub_type', $this->category],
                ['start_date', '<=', $date],
                ['end_date', '>=', $date]
            ]);
        })->where('client_employee_id', $employee->id)->with('clientEmployeeLeaveManagementByMonth')->first();

        if($approvePage) {
            if($clientEmployeeLeaveManagement) {
                $firstClientEmployeeLeaveManagementByMonth = $clientEmployeeLeaveManagement->clientEmployeeLeaveManagementByMonth->where("start_date", '<=', $date)
                    ->where("end_date", '>=', $date)->first();
                // The number of paid leave hours filtered by the filter is accumulated by each month of the year
                if ($firstClientEmployeeLeaveManagementByMonth) {
                    $arrayParamTotalAndRemain['year_paid_leave_count'] = round($firstClientEmployeeLeaveManagementByMonth->remaining_entitlement, 2);
                    $arrayParamTotalAndRemain = json_encode($arrayParamTotalAndRemain);
                }
            } else {
                $clientEmployee = ClientEmployee::where('id', $this->client_employee_id)->first();
                $arrayParamTotalAndRemain['year_paid_leave_count'] = round($clientEmployee->year_paid_leave_count, 2);
                $arrayParamTotalAndRemain = json_encode($arrayParamTotalAndRemain);
            }
        } else {
            if($clientEmployeeLeaveManagement) {
                $firstClientEmployeeLeaveManagementByMonth = $clientEmployeeLeaveManagement->clientEmployeeLeaveManagementByMonth->where("start_date", '<=', $date)
                    ->where("end_date", '>=', $date)->first();
                if ($firstClientEmployeeLeaveManagementByMonth) {
                    // The number of paid leave hours filtered by the filter is accumulated by each month of the year
                    $arrayParamTotalAndRemain['total_leave_paid_hour_year'] = round(max($clientEmployeeLeaveManagement->entitlement - $firstClientEmployeeLeaveManagementByMonth->remaining_entitlement, 0), 2);
                    $arrayParamTotalAndRemain['remain_total_leave_paid_hour_year'] = round(max($firstClientEmployeeLeaveManagementByMonth->remaining_entitlement, 0), 2);
                    $arrayParamTotalAndRemain = json_encode($arrayParamTotalAndRemain);
                }
            }
        }


        return $arrayParamTotalAndRemain;
    }

    private function getWorkScheduleGroup($condition)
    {
        return WorkScheduleGroup::query()
            ->where('timesheet_from', '<=', $condition['date'])
            ->where('timesheet_to', '>=', $condition['date'])
            ->where('work_schedule_group_template_id', $condition['work_schedule_group_template_id'])
            ->first();
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();
        if (!$user->isInternalUser()) {
            $query->belongToClientTo($user->clientEmployee);
            // Init list permission
            $listPermission = ["manage-employee", "manage-timesheet"];
            // Check that the permission mode is enabled
            $settingAdvancedPermissionFlow = $user->getSettingAdvancedPermissionFlow($user->client_id);
            if ($settingAdvancedPermissionFlow) {
                $initPermissionWorkRegister = [
                    "advanced-manage-timesheet-summary-read",
                    "advanced-manage-timesheet-working-read",
                    "advanced-manage-timesheet-leave-read",
                    "advanced-manage-timesheet-overtime-read",
                    "advanced-manage-timesheet-outside-working-wfh-read",
                    "advanced-manage-timesheet-timesheet-shift-read"
                ];

                $listPermission = array_merge($listPermission, $initPermissionWorkRegister);
            }
            // Check user have permission in listPermission
            if ($user->hasAnyPermission($listPermission)) {
                return $query;
            } else {
                return $query->where(function ($query) use ($user) {
                    $query->whereHas("approves", function ($query) {
                        return $query->authUserAccessible();
                    });
                    $query->orWhere('client_employee_id', '=', $user->clientEmployee->id);
                });
            }
        } else {
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                    return $query->whereNull($this->getTable() . '.id');
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return $query;
            }
        }
    }

    public static function statuses()
    {
        return array(
            'new' => 'Mới',
            'pending' => 'Chưa duyệt',
            'approved' => 'Đã duyệt',
            'canceled' => 'Đã huỷ',
            'canceled_approved' => 'Huỷ yêu cầu'
        );
    }

    /**
     * Create or update work time register timesheet.
     *
     * @return boolean
     */
    public function createOrUpdateOTWorkTimeRegisterTimesheet()
    {
        $clientEmployee = ClientEmployee::select('id', 'client_id', 'work_schedule_group_template_id')
            ->where('id', $this->client_employee_id)->first();
        $periods = $this->periods;
        if (!$clientEmployee || !$periods) {
            return false;
        }

        WorkTimeRegisterTimesheet::where('register_id', $this->id)->update(['time_values' => 0.00]);
        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $clientEmployee->client_id)->first();
        $client = Client::select('id', 'ot_min_time_block')->where('id', $clientEmployee->client_id)->first();
        $otMinTimeBlock = $client->ot_min_time_block ?: 1;
        $dayBeginMark = $clientWorkFlowSetting->getTimesheetDayBeginAttribute();
        $dateList = [];
        if ($clientWorkFlowSetting->enable_overtime_request) {
            foreach ($periods as $period) {
                $dateList[$period->date_time_register] = 1;
                $dayStart = Carbon::parse($period->date_time_register . ' ' . $dayBeginMark);
                $dayEnd = $dayStart->clone()->addDay();
                if ($dayStart->isAfter($period->start_datetime)) {
                    $yesterday = Carbon::parse($period->date_time_register)->subDay()->format('Y-m-d');
                    $dateList[$yesterday] = 1;
                }
                if ($dayEnd->isBefore($period->end_datetime)) {
                    $nextDay = Carbon::parse($period->date_time_register)->addDay()->format('Y-m-d');
                    $dateList[$nextDay] = 1;
                }
            }
            foreach ($dateList as $date => $value) {
                $timeSheet = $clientEmployee->touchTimesheet($date);

                $dayStart = Carbon::parse($date . ' ' . $dayBeginMark);
                $dayEnd = $dayStart->clone()->addDay();
                $dayPeriod = PeriodHelper::makePeriod($dayStart, $dayEnd);

                $overTimeHours = 0;
                $midnightOvertimeHours = 0;

                $midnightOTPeriods = PeriodHelper::getNightPeriodsForDay($date, $dayBeginMark);

                if ($timeSheet->isUsingMultiShift($clientWorkFlowSetting)) {
                    /** Get work schedule period and working period */
                    $schedulePeriods = new PeriodCollection();
                    $convertedInOutPeriods = new PeriodCollection();
                    foreach ($timeSheet->timesheetShiftMapping as $item) {
                        $schedulePeriodsWithoutRest = PeriodHelper::subtract($item->schedule_shift_period, $item->rest_shift_period);
                        $schedulePeriods = PeriodHelper::merge2Collections($schedulePeriods, $schedulePeriodsWithoutRest);
                        /** Work schedule is only allowed in the day period */
                        $schedulePeriods = $schedulePeriods->overlapSingle(new PeriodCollection($dayPeriod));

                        $convertedInOutPeriodsWithoutRest = PeriodHelper::subtract($item->converted_in_out_period, $item->rest_shift_period);
                        $convertedInOutPeriods = PeriodHelper::merge2Collections($convertedInOutPeriods, $convertedInOutPeriodsWithoutRest);
                    }

                    [$overTimeHours, $midnightOvertimeHours] = $this->getOverTimeFromMultipleShift($schedulePeriods, $midnightOTPeriods, $convertedInOutPeriods, $dayPeriod, (int)$otMinTimeBlock);

                } else {
                    foreach ($periods as $period) {
                        $otTimeMinuteTemp = 0;
                        $otMidnightHourTemp = 0;
                        [$checkIn, $checkOut] = $timeSheet->getCheckInOutCarbonByRequestPeriod($period, $clientEmployee, null, $clientWorkFlowSetting->flexible_timesheet_setting);
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

                        if (!$otPeriods || $otPeriods->isEmpty()) continue;

                        foreach ($otPeriods as $otPeriod) {
                            //If this request isn't skip logic => need to overlap with actual checkin/checkout
                            if (!$this->skip_logic) {
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
                }

                if ($this->type == 'makeup_request') {
                    WorkTimeRegisterTimesheet::updateOrCreate([
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'type' => WorkTimeRegisterTimesheet::OT_MAKEUP_HOURS_TYPE,
                    ], [
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'client_employee_id' => $this->client_employee_id,
                        'type' => WorkTimeRegisterTimesheet::OT_MAKEUP_HOURS_TYPE,
                        'time_values' => $overTimeHours ?? 0
                    ]);
                } else {
                    WorkTimeRegisterTimesheet::updateOrCreate([
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'type' => WorkTimeRegisterTimesheet::OT_TYPE,
                    ], [
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'client_employee_id' => $this->client_employee_id,
                        'type' => WorkTimeRegisterTimesheet::OT_TYPE,
                        'time_values' => $overTimeHours ?? 0
                    ]);

                    WorkTimeRegisterTimesheet::updateOrCreate([
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'type' => WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE,
                    ], [
                        'register_id' => $this->id,
                        'timesheet_id' => $timeSheet->id,
                        'client_employee_id' => $this->client_employee_id,
                        'type' => WorkTimeRegisterTimesheet::OT_MIDNIGHT_TYPE,
                        'time_values' => $midnightOvertimeHours ?? 0
                    ]);
                }
                $timeSheet->overtime_hours = 0;
                $timeSheet->midnight_overtime_hours = 0;
                $timeSheet->makeup_hours = 0;
                $timeSheet->reCalculateOT();
                $timeSheet->saveQuietly();
            }
        }
        return true;
    }

    /**
     * Delete work time register timesheet.
     *
     * @return boolean
     */
    public function reCalculatedOTWhenCancel()
    {
        $wrts = WorkTimeRegisterTimesheet::where('register_id', $this->id)->get();
        if (empty($wrts)) {
            return false;
        }

        foreach ($wrts as $wrt) {
            $wrt->delete();
            $ts = Timesheet::find($wrt['timesheet_id']);
            $ts->recalculate();
            $ts->saveQuietly();
        }
        return true;
    }

    public function getWFHAttachmentsAttribute()
    {
        $media = $this->getMedia("WFH");
        $wfh_attachments = [];

        if (count($media) > 0) {
            foreach ($media as $key => $item) {
                $wfh_attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'size' => $item->size,
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $wfh_attachments;
    }

/*
 * This is temporary way.Then I add status column in work_time_register_period.
 */
    public function getStatusAttribute()
    {
        if ($this->realStatus) {
            return $this->attributes['status'];
        } else {
            $isWaitCancel = $this->periods->contains('is_cancellation_approval_pending', 1);
            if ($isWaitCancel) {
                return Constant::WAIT_CANCEL_APPROVE;
            }

            return $this->attributes['status'];
        }
    }

    public function setRealStatusAttribute($value)
    {
        $this->realStatus = $value;
    }

    /**
     * Getting request periods by overlapping request periods with the schedule period.
     *
     * @param PeriodCollection $schedulePeriods
     * @param PeriodCollection $midnightOTPeriods
     * @param PeriodCollection $convertedInOutPeriods
     * @param Period           $dayPeriod,
     * @param int              $otMinTimeBlock
     *
     * @return PeriodCollection
     */
    public function getOverTimeFromMultipleShift(
        PeriodCollection $schedulePeriods,
        PeriodCollection $midnightOTPeriods,
        PeriodCollection $convertedInOutPeriods,
        Period           $dayPeriod,
        int              $otMinTimeBlock
    ): array
    {
        $overTimeHours = 0;
        $midnightOvertimeHours = 0;

        PeriodHelper::union($convertedInOutPeriods);

        foreach ($this->periods as $period) {
            /** @var WorkTimeRegisterPeriod $period */
            $requestPeriod = $period->getPeriod()->overlapSingle($dayPeriod);
            if (PeriodHelper::countMinutes($requestPeriod) <= 0) {
                continue;
            }

            $breakPeriod = Period::make($period->start_break_datetime, $period->end_break_datetime, Precision::SECOND, Boundaries::EXCLUDE_ALL);
            $requestPeriod = $requestPeriod->diffSingle($breakPeriod);


            if (!$this->skip_logic) {
                if ($convertedInOutPeriods->isEmpty()) {
                    $requestPeriod = new PeriodCollection();
                }
                $requestPeriod = $requestPeriod->overlap($convertedInOutPeriods);
            }

            if ($requestPeriod->isEmpty()) {
                continue;
            }

            $requestPeriod = PeriodHelper::subtractPeriodCollection($requestPeriod, $schedulePeriods);

            $overTimeMinutes = $requestPeriod->reduce(function ($carry, $item) {
                $carry += PeriodHelper::countMinutes($item);
                return $carry;
            }, 0);
            $overTimeMinutes = floor($overTimeMinutes / $otMinTimeBlock) * $otMinTimeBlock;

            $otTimeHourTemp = round($overTimeMinutes / 60, 2, PHP_ROUND_HALF_DOWN);
            $overTimeHours += $otTimeHourTemp;

            $midnightOverTimePeriods = $requestPeriod->overlapSingle($midnightOTPeriods);
            foreach ($midnightOverTimePeriods as $overlapPeriod) {
                $midnightOvertimeHours += PeriodHelper::countHours($overlapPeriod);
            }

            $period->so_gio_tam_tinh = $otTimeHourTemp;
            $period->save();
        }

        return [$overTimeHours, $midnightOvertimeHours];
    }
}
