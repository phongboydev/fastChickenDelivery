<?php

namespace App\Models;

use App\DTO\TimesheetSchedule;
use App\GraphQL\Queries\GetTimesheetByWorkScheduleGroup;
use App\GraphQL\Queries\GetTimesheetSchedules;
use App\Jobs\RefreshClientEmployeeTimesheetJob;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\HanetHelper;
use App\Support\MediaTrait;
use App\Support\PeriodHelper;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Support\TimesheetsHelper;
use Spatie\Period\Period;
use Spatie\Period\Precision;

/**
 * @property string $id
 * @property string $client_id
 * @property string $full_name
 * @property string $code
 * @property string $probation_start_date
 * @property string $probation_end_date
 * @property string $official_contract_signing_date
 * @property string $type_of_employment_contract
 * @property string $salary
 * @property string $allowance_for_responsibilities
 * @property string $fixed_allowance
 * @property int $is_tax_applicable
 * @property int $is_insurance_applicable
 * @property int $number_of_dependents
 * @property string $bank_account
 * @property string $bank_account_number
 * @property string $bank_name
 * @property string $bank_branch
 * @property string $social_insurance_number
 * @property string $date_of_birth
 * @property string $sex
 * @property string $department
 * @property string $position
 * @property string $title
 * @property string $workplace
 * @property string $marital_status
 * @property string $salary_for_social_insurance_payment
 * @property string $effective_date_of_social_insurance
 * @property string $medical_care_hospital_name
 * @property string $medical_care_hospital_code
 * @property string $nationality
 * @property string $nation
 * @property string $id_card_number
 * @property string $is_card_issue_date
 * @property string $id_card_issue_place
 * @property string $birth_place_address
 * @property string $birth_place_street
 * @property string $birth_place_wards
 * @property string $birth_place_district
 * @property string $birth_place_city_province
 * @property string $resident_address
 * @property string $resident_street
 * @property string $resident_wards
 * @property string $resident_district
 * @property string $resident_city_province
 * @property boolean $resident_status
 * @property string $contact_address
 * @property string $contact_street
 * @property string $contact_wards
 * @property string $contact_district
 * @property string $contact_city_province
 * @property string $contact_phone_number
 * @property string $household_head_info
 * @property string $household_code
 * @property string $household_head_fullname
 * @property string $household_head_id_card_number
 * @property string $household_head_date_of_birth
 * @property string $household_head_relation
 * @property string $household_head_phone
 * @property string $resident_record_number
 * @property string $resident_record_type
 * @property string $resident_village
 * @property string $resident_commune_ward_district_province
 * @property string $status
 * @property string $quitted_at
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property string $role
 * @property string $mst_code
 * @property string $hour_wage
 * @property string onboard_date
 */
class ClientEmployee extends Model implements HasMedia
{

    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, SoftDeletes, LogsActivity, HasAssignment;
    use HasFactory;

    /**
     * @var array|string[]
     */
    protected static array $logAttributes = ['*'];
    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'client_employees';
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
        'client_id',
        'full_name',
        'code',
        'probation_start_date',
        'probation_end_date',
        'official_contract_signing_date',
        'type_of_employment_contract',
        'salary',
        'allowance_for_responsibilities',
        'fixed_allowance',
        'is_tax_applicable',
        'is_insurance_applicable',
        'number_of_dependents',
        'bank_account',
        'bank_account_number',
        'bank_name',
        'bank_branch',
        'bank_code',
        'social_insurance_number',
        'date_of_birth',
        'sex',
        'department',
        'client_department_id',
        'position',
        'client_position_id',
        'title',
        'workplace',
        'marital_status',
        'salary_for_social_insurance_payment',
        'effective_date_of_social_insurance',
        'medical_care_hospital_name',
        'medical_care_hospital_code',
        'nationality',
        'nation',
        'id_card_number',
        'is_card_issue_date',
        'id_card_issue_place',
        'birth_place_address',
        'birth_place_street',
        'birth_place_wards',
        'birth_place_district',
        'birth_place_city_province',
        'resident_address',
        'resident_street',
        'resident_wards',
        'resident_district',
        'resident_city_province',
        'contact_address',
        'contact_street',
        'contact_wards',
        'contact_district',
        'contact_city_province',
        'contact_phone_number',
        'contract_no',
        'household_head_info',
        'household_code',
        'household_head_fullname',
        'household_head_id_card_number',
        'household_head_date_of_birth',
        'household_head_relation',
        'household_head_phone',
        'resident_record_number',
        'resident_record_type',
        'resident_village',
        'resident_commune_ward_district_province',
        'resident_status',
        'status', 'quitted_at',
        'foreigner_job_position',
        'foreigner_contract_status',
        'education_level',
        'user_id', 'role',
        'created_at',
        'updated_at',
        'deleted_at',
        'work_schedule_group_template_id',
        'last_year_paid_leave_count',
        'last_year_paid_leave_expiry',
        'year_paid_leave_count',
        'year_paid_leave_expiry',
        'next_year_paid_leave_count',
        'next_year_paid_leave_expiry',
        'leave_balance',
        'currency',
        'mst_code',
        'career',
        'hour_wage',
        'checkin_wifi',
        'checkin_camera',
        'checkin_input',
        'case_import_paidleave',
        'start_import_paidleave',
        'started_import_paidleave',
        'hours_import_paidleave',
        'checkin_input',
        'timesheet_exception',
        'is_involved_payroll',
        'date_of_entry',
        'religion',
        'blood_group',
        'spouse_working_at_company',
        'educational_qualification',
        'year_of_graduation',
        'major',
        'certificate_1',
        'certificate_2',
        'certificate_3',
        'certificate_4',
        'certificate_5',
        'certificate_6',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'commuting_transportation',
        'vehicle_license_plate',
        'locker_number'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => Constant::CLIENT_EMPLOYEE_STATUS_WORKING,
        'role' => Constant::ROLE_CLIENT_STAFF,
    ];

    public function getYearPaidLeaveStartOriginalAttribute()
    {
        $thisYear = date('Y');

        $clientEmployeeLeaveManagement = $this->clientEmployeeLeaveManagement()
            ->where('year', $thisYear)
            ->first();

        return $clientEmployeeLeaveManagement ? $clientEmployeeLeaveManagement->start_date . " 00:00:00" : "{$thisYear}-01-01 00:00:00";
    }

    public function getYearPaidLeaveStartAttribute()
    {
        $thisYearPaidLeaveStart = $this->year_paid_leave_start_original;

        if ($this->last_year_paid_leave_expiry && Carbon::now()->lte($this->last_year_paid_leave_expiry)) {
            $lastYearPaidLeaveExpiry = Carbon::parse($this->last_year_paid_leave_expiry);
            $yearPaidLeaveExpiry = Carbon::parse($this->year_paid_leave_expiry);
            $yearPaidLeaveStart = Carbon::parse($thisYearPaidLeaveStart);

            $lastYearPaidLeavePeriod = Period::make($yearPaidLeaveStart, $lastYearPaidLeaveExpiry, Precision::SECOND);
            $yearPaidLeavePeriod = Period::make($yearPaidLeaveStart, $yearPaidLeaveExpiry, Precision::SECOND);

            $diffSingle = $lastYearPaidLeavePeriod->diffSingle($yearPaidLeavePeriod);
            if ($diffSingle) {
                return $diffSingle[0]->getStart()->format('Y-m-d H:i:s');
            }
        }

        return $thisYearPaidLeaveStart;
    }

    public function getLastYearPaidLeaveStartAttribute()
    {
        if ($this->last_year_paid_leave_expiry && Carbon::now()->lte($this->last_year_paid_leave_expiry) && $this->last_year_paid_leave_count > 0) {
            $thisYear = date('Y');

            $clientEmployeeLeaveManagement = $this->clientEmployeeLeaveManagement()
                ->where('year', $thisYear)
                ->first();

            $thisYearPaidLeaveStart = $clientEmployeeLeaveManagement ? $clientEmployeeLeaveManagement->start_date . " 00:00:00" : "{$thisYear}-01-01 00:00:00";

            $lastYearPaidLeaveExpiry = Carbon::parse($this->last_year_paid_leave_expiry);
            $yearPaidLeaveExpiry = Carbon::parse($this->year_paid_leave_expiry);
            $yearPaidLeaveStart = Carbon::parse($thisYearPaidLeaveStart);

            $lastYearPaidLeavePeriod = Period::make($yearPaidLeaveStart, $lastYearPaidLeaveExpiry, Precision::SECOND);
            $yearPaidLeavePeriod = Period::make($yearPaidLeaveStart, $yearPaidLeaveExpiry, Precision::SECOND);

            $overlapSingle = $lastYearPaidLeavePeriod->overlapSingle($yearPaidLeavePeriod);
            if ($overlapSingle) {
                return $overlapSingle->getStart()->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    public function getNextYearPaidLeaveStartAttribute()
    {
        if ($this->next_year_paid_leave_expiry && Carbon::now()->lte($this->next_year_paid_leave_expiry)) {
            $nextYear = date('Y', strtotime('+1 year'));

            $clientEmployeeLeaveManagement = $this->clientEmployeeLeaveManagement()
                ->where('year', $nextYear)
                ->first();

            $getNextYearPaidLeaveStart = $clientEmployeeLeaveManagement ? $clientEmployeeLeaveManagement->start_date . " 00:00:00" : "{$nextYear}-01-01 00:00:00";

            $nextYearPaidLeaveStart = Carbon::parse($getNextYearPaidLeaveStart);
            $nextYearPaidLeaveExpiry = Carbon::parse($this->next_year_paid_leave_expiry);
            $yearPaidLeaveStart = Carbon::parse($this->year_paid_leave_start);
            $yearPaidLeaveExpiry = Carbon::parse($this->year_paid_leave_expiry);

            $nextYearPaidLeavePeriod = Period::make($nextYearPaidLeaveStart, $nextYearPaidLeaveExpiry, Precision::SECOND);
            $yearPaidLeavePeriod = Period::make($yearPaidLeaveStart, $yearPaidLeaveExpiry, Precision::SECOND);

            $diffSingle = $yearPaidLeavePeriod->diffSingle($nextYearPaidLeavePeriod);
            if ($diffSingle) {
                return $diffSingle[1]->getStart()->format('Y-m-d H:i:s');
            }
            return $getNextYearPaidLeaveStart;
        }

        return null;
    }

    public function getGroupApprovalAttribute()
    {
        return $this->clientEmployeeGroupAssignment->where('approval', 1)->pluck('client_employee_group_id')->toArray() ?: ['0'];
    }

    public function getDepartmentIDAttribute()
    {
        return ClientDepartment::where(['department' => $this->department, 'position' => $this->position])->value('id');
    }

    public function getOnboardDateAttribute()
    {
        return $this->client()->value('seniority_contract_type') == 'thuviec' ? $this->probation_start_date : $this->official_contract_signing_date;
    }

    public function getClientDepartmentNameAttribute()
    {
        return ClientDepartment::where(['id' => $this->client_department_id])->value('department');
    }

    public function getClientPositionNameAttribute()
    {
        return ClientPosition::where(['id' => $this->client_position_id])->value('name');
    }

    public function getClientDepartmentCodeAttribute()
    {
        return ClientDepartment::where(['id' => $this->client_department_id])->value('code');
    }

    public function getClientPositionCodeAttribute()
    {
        return ClientPosition::where(['id' => $this->client_position_id])->value('code');
    }

    public function getClientCodeAttribute()
    {
        return $this->client()->withTrashed()->value('code');
    }

    public function getMediaModel()
    {
        return $this->getMedia('avatar');
    }

    public function getAvatarPathAttribute()
    {
        $media = $this->getFirstMedia('avatar');
        if (!empty($media)) {
            return $this->getPublicTemporaryUrl($media);
        } else {
            return Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT;
        }
    }

    public function getAvatarHanetAttribute()
    {
        $media = $this->getFirstMedia('avatar_hanet');
        if (!empty($media)) {
            return $this->getPublicTemporaryUrl($media);
        } else {
            return Constant::CLIENT_EMPLOYEE_AVATAR_DEFAULT;
        }
    }

    public function getAvatarPathLargeAttribute()
    {
        $media = $this->getMedia('avatar');

        if (count($media) > 0) {
            return $this->getMediaPathAttribute() . $media[0]->getPath();
        } else {
            return '/img/theme/man.png';
        }
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10);
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function workScheduleGroupTemplate()
    {
        return $this->belongsTo('App\Models\WorkScheduleGroupTemplate');
    }

    /**
     * @return BelongsTo
     */
    public function clientDepartment(): BelongsTo
    {
        return $this->belongsTo(ClientDepartment::class, 'client_department_id');
    }

    /**
     * @return BelongsTo
     */
    public function clientPosition(): BelongsTo
    {
        return $this->belongsTo(ClientPosition::class, 'client_position_id');
    }

    public function contract()
    {
        return $this->hasMany(ClientEmployeeContract::class);
    }

    public function getWorkSchedule($log_date)
    {
        $ws = WorkSchedule::query()
            ->where('client_id', $this->client_id)
            ->whereHas(
                'workScheduleGroup',
                function ($group) {
                    $group->where(
                        'work_schedule_group_template_id',
                        $this->work_schedule_group_template_id
                    );
                }
            )
            ->where('schedule_date', $log_date)
            ->with('workScheduleGroup')
            ->first();
        if (!$ws) {
            return null;
        }

        $ts = (new Timesheet)->findTimeSheet($this->id, $log_date);
        if ($ts) {
            $ws = $ts->getShiftWorkSchedule($ws);
        }
        return $ws;
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function scopeDoesntHaveShift($query, $args)
    {
        $dateRange = $args['dateRange'];
        return $query->whereDoesntHave('timesheets', function ($query) use ($dateRange) {
            $query->where('shift_enabled', 1)
                ->whereBetween('log_date', $dateRange);
        });
    }

    public function contracts()
    {
        return $this->hasMany(ClientEmployeeContract::class)
            ->whereNotIn('contract_type', ['phu-luc-hop-dong-lao-dong', 'khac'])
            ->latest();
    }

    public function oldestContracts()
    {
        return $this->hasMany(ClientEmployeeContract::class)
            ->whereNotIn('contract_type', ['phu-luc-hop-dong-lao-dong', 'khac'])
            ->oldest();
    }

    public function clientEmployeeGroupAssignments()
    {
        return $this->hasMany(ClientEmployeeGroupAssignment::class);
    }

    public function calculationSheetClientEmployee()
    {
        return $this->hasMany(CalculationSheetClientEmployee::class);
    }

    public function clientEmployeeGroupAssignment()
    {
        return $this->hasMany(ClientEmployeeGroupAssignment::class);
    }

    public function hanetPerson()
    {
        return $this->hasOne(HanetPerson::class);
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function scopeIsClientTo($query, $clientId)
    {
        return $query->where("client_id", $clientId);
    }

    /**
     * Check if this employee is staff of provided leader
     *
     * @param  ClientEmployee|string  $clientEmployeeLeader
     */
    public function isAssignedFor($clientEmployeeLeader)
    {
        $leaderId = null;
        if (is_string($clientEmployeeLeader)) {
            $leaderId = $clientEmployeeLeader;
        } elseif ($clientEmployeeLeader instanceof ClientEmployee) {
            $leaderId = $clientEmployeeLeader->id;
        }
        if (!$leaderId) {
            throw new \InvalidArgumentException('Given $clientEmployeeLeader parameter must be a string or instance of ClientEmployee model');
        }
        return $this->assignmentsAsStaff()->where("leader_id", $leaderId)->exists();
    }

    public function assignmentsAsStaff()
    {
        return $this->hasMany(ClientAssignment::class, 'staff_id');
    }

    /**
     * @param $query
     * @param $leaderId
     */
    public function scopeStaffOf($query, $leaderId)
    {
        $query->whereHas('assignmentsAsStaff', function (Builder $query) use ($leaderId) {
            $model = new ClientAssignment();
            $query->where("{$model->getTable()}.leader_id", $leaderId);
        });
    }

    /**
     * @param $query
     * @param $leaderId
     */
    public function scopeLeaderOf($query, $staffId)
    {
        $query->whereHas('assignmentsAsLeader', function (Builder $query) use ($staffId) {
            $model = new ClientAssignment();
            $query->where("{$model->getTable()}.staff_id", $staffId);
        });
    }

    public function assignmentsAsLeader()
    {
        return $this->hasMany(ClientAssignment::class, 'leader_id');
    }

    public function scopeHasAssignmentsWith($query, $clientEmployeeId)
    {
        return $query->where(function ($query) use ($clientEmployeeId) {
            $query->leaderOf($clientEmployeeId);
        })->orWhere(function ($query) use ($clientEmployeeId) {
            $query->staffOf($clientEmployeeId);
        });
    }

    public function findClientEmployee($id)
    {
        return $this->whereId($id)->firstOrFail()->toArray();
    }

    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientEmployee::class,
            'client_assignments',
            'staff_id',
            'leader_id'
        );
    }

    public function scopeSimplifiedClientEmployees($query)
    {
        $user = Auth::user();
        return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
    }

    public function scopeStatus($query)
    {
        return $query->where('status', '!=', Constant::CLIENT_EMPLOYEE_STATUS_QUIT);
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            // init permission
            $normalPermissions = ['manage-employee', 'manage-timesheet', 'manage-payroll', 'CLIENT_REQUEST_CLAIM_BHXH', 'CLIENT_REQUEST_OFF'];
            $advancedPermissions = [
                'advanced-manage-employee-list-read', 'advanced-manage-timesheet-summary-read',
                'advanced-manage-timesheet-working-read', 'advanced-manage-timesheet-leave-read', 'advanced-manage-timesheet-overtime-read',
                'advanced-manage-timesheet-outside-working-wfh-read', 'advanced-manage-timesheet-timesheet-shift-read', 'advanced-manage-payroll-list-read',
                'CLIENT_REQUEST_CLAIM_BHXH', 'CLIENT_REQUEST_OFF', 'advanced-manage-payroll-info-read'
            ];
            if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow($user->client_id))) {
                return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
            }

            return $query->where($this->getTable() . '.user_id', '=', $user->id);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployeeSalary()
    {
        return $this->belongsTo('App\Models\ClientEmployeeSalary');
    }

    /**
     * @return HasMany
     */
    public function clientEmployeeSalaryHistories()
    {
        return $this->hasMany(ClientEmployeeSalaryHistory::class);
    }

    /**
     * @return HasOne
     */
    public function currentSalary()
    {
        return $this->hasOne(ClientEmployeeSalaryHistory::class)
            ->whereDate('start_date', '<=', Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d'))
            ->latest('start_date');
    }

    public function clientEmployeeTrainingSeminars()
    {
        return $this->belongsToMany('App\Models\ClientEmployeeTrainingSeminar');
    }

    public function countSeminarsOfEmployee()
    {
        return $this->hasMany(ClientEmployeeTrainingSeminar::class);
    }
    public function getEmployeeSeminarsCountAttribute()
    {
        return $this->countSeminarsOfEmployee()->count();
    }

    public function getListGroupAssignmentOfClientEmployeeAttribute()
    {
        $data = $this->clientEmployeeGroupAssignment;
        $isAdvancedPermission = $this->client->clientWorkflowSetting->advanced_permission_flow;
        $listGroup = ['0'];
        $authUser = Auth::user();
        if ($isAdvancedPermission) {
            $approveFlow = ApproveFlow::whereHas('approveFlowUsers', function ($query) use($authUser) {
                $query->where('user_id', $authUser->id);
            })
                ->where('client_id', $authUser->client_id)
                ->where('flow_name', 'like', '%' . '-read')
                ->get()->keyBy('group_id');
            if (!$approveFlow->isEmpty()) {
                $listGroup = [];
                $data->each(function ($item) use ($approveFlow, &$listGroup) {
                    if ($approveFlow->has($item->client_employee_group_id)) {
                        $listGroup[] = [
                            'label' => $item->clientEmployeeGroup->name,
                            'value' => $item->client_employee_group_id,
                        ];
                    }
                });
            }

        } else {
            if ($data->isNotEmpty()) {
                $listGroup = [];
                $data->each(function ($item) use (&$listGroup) {
                    $listGroup[] = [
                        'label' => $item->clientEmployeeGroup->name,
                        'value' => $item->client_employee_group_id,
                    ];

                });
            }
        }

        return count($listGroup) > 0 ? $listGroup : ['0'];
    }

    public function training_seminar_schedule()
    {
        return $this->hasMany(TrainingSeminarSchedule::class);
    }

    public function training_seminar_attendance()
    {
        return $this->hasMany(TrainingSeminarAttendance::class);
    }

    public function training_seminars()
    {
        return $this->hasMany(TrainingSeminar::class);
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

    public function assignmentsAsJobboard()
    {
        return $this->hasMany(JobboardAssignment::class);
    }

    public function clientProject()
    {
        return $this->belongsToMany(ClientProject::class, 'client_project_employees');
    }

    /**
     * Check in/out automatically
     *
     * @param  string|null  $logDate
     * @param  string  $time
     *
     * @return void
     */
    public function checkTimeAuto(string $date = null, string $time = "", string $source = "App")
    {
        $workflowSetting = $this->client->clientWorkflowSetting;
        $dayBeginMark = $workflowSetting->getTimesheetDayBeginAttribute();

        // process input
        $checkDate = $date ? $date : Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateString();
        $checkTime = $time ? $time : Carbon::now(Constant::TIMESHEET_TIMEZONE)->format("H:i");
        $now = Carbon::parse($checkDate . ' ' . $checkTime . ":00", Constant::TIMESHEET_TIMEZONE);

        $nextMarkMoment = Carbon::parse($dayBeginMark, Constant::TIMESHEET_TIMEZONE); // today's day begin mark
        $nextMarkMoment = $now->clone()
            ->setHour($nextMarkMoment->hour)
            ->setMinute($nextMarkMoment->minute);

        $nextDay = -1;
        if ($now->isBefore($nextMarkMoment)) {
            $now->subtract('day', 1);
            $nextDay = 1;
        }

        $logDate = $now->toDateString();
        $timesheet = (new Timesheet)->findTimeSheet($this->id, $logDate);

        if (!$timesheet) {
            $timesheet = TimesheetsHelper::createTimeSheetPerDate($this->id, $logDate);
        }

        Checking::upsert([
            'client_id' => $this->client_id,
            'client_employee_id' => $this->id,
            'checking_time' => $now->toDateTimeString(),
            'source' => $source
        ], ['client_employee_id', 'checking_time']);

        $clientWorkFlowSetting = ClientWorkflowSetting::where('client_id', $this->client_id)->first();
        if ($timesheet && $timesheet->isUsingMultiShift($clientWorkFlowSetting)) {
            if ($nextDay == 1) {
                $now->addDay();
            }
            $timesheet->checkTimeWithMultiShift($now, $source);
            if (!empty($workflowSetting)) {
                $timesheet->calculateMultiTimesheet($workflowSetting);
                $timesheet->saveQuietly();
            }
        } else {
            //checkin
            if (!$timesheet || !$timesheet->check_in) {
                return $this->checkIn($logDate, $time, $nextDay, $source);
            }

            $checkIn = $timesheet->start_next_day ? Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in . ':00', Constant::TIMESHEET_TIMEZONE)->addDay() : Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_in . ':00', Constant::TIMESHEET_TIMEZONE);
            if ($now->isBefore($checkIn)) {
                return $this->checkIn($logDate, $time, $nextDay, $source);
            }

            //checkout
            if (!$timesheet->check_out) {
                return $this->checkOut($logDate, $time, $nextDay, $source);
            }

            $checkOut = $timesheet->next_day ? Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_out . ':00', Constant::TIMESHEET_TIMEZONE)->addDay() : Carbon::parse($timesheet->log_date . ' ' . $timesheet->check_out . ':00', Constant::TIMESHEET_TIMEZONE);
            if ($now->isAfter($checkOut)) {
                return $this->checkOut($logDate, $time, $nextDay, $source);
            }
        }
    }

    /**
     * Shorthand for checkTime
     *
     * @param  string  $date
     */
    public function checkIn(string $date = null, string $time = "", int $next_day = -1, string $source = "App")
    {
        $logDate = $date ?? Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateString();
        return $this->checkTime($logDate, false, $time, $next_day, $source);
    }

    /**
     * @param  string  $logDate
     * @param  bool  $out
     * @param  string  $time  HH:mm format
     */
    public function checkTime(string $logDate, bool $out = false, string $time = "", int $next_day = -1, string $source = "App")
    {
        // TODO UTC timezone may affect this method
        $now = $time ?: PeriodHelper::getHourString(Carbon::now(Constant::TIMESHEET_TIMEZONE));
        $timesheet = (new Timesheet)->findTimeSheet($this->id, $logDate);

        // checkSkipHanet
        $checkSkipHanet = HanetHelper::checkSkipHanet($this->client_id, $timesheet->skip_hanet, $source, $timesheet->id);

        if ($checkSkipHanet) {
            logger(__METHOD__ . ": skipHanet [{$timesheet->skip_hanet}] -- source: {$source} -- clientEmployeeID {$this->id} -- timesheetShiftMappingID: {$timesheet->id} -- logDate: {$logDate}");
            return;
        }

        if (!$timesheet) {
            $timesheet = new Timesheet();
            $timesheet->client_employee_id = $this->id;
            $timesheet->log_date = $logDate;
            $timesheet->activity = "activity";
            $timesheet->work_place = "work_place";
            $timesheet->working_hours = 0;
            $timesheet->overtime_hours = 0;
            $timesheet->check_in = "";
            $timesheet->check_out = "";
            $timesheet->leave_type = "early_leave";
            $timesheet->attentdant_status = "doing";
            $timesheet->note = "";
            $timesheet->reason = "";
            $timesheet->next_day = 0;
        }

        if ($out === true) {
            $timesheet->check_out = $now;
            if ($next_day != -1) {
                $timesheet->next_day = $next_day;
            }
        } else {
            $timesheet->check_in = $now;
            if ($next_day != -1) {
                $timesheet->start_next_day = $next_day;
            }

            $originalIn = $timesheet->getOriginal('check_in') ? ($timesheet->getOriginal('start_next_day') ? Carbon::parse($timesheet->getOriginal('check_in') . ":00", Constant::TIMESHEET_TIMEZONE)->addDay() : Carbon::parse($timesheet->getOriginal('check_in') . ":00", Constant::TIMESHEET_TIMEZONE)) : null;
            $nowIn = $timesheet->start_next_day ? Carbon::parse($now . ":00", Constant::TIMESHEET_TIMEZONE)->addDay() : Carbon::parse($now . ":00", Constant::TIMESHEET_TIMEZONE);
            if (empty($originalIn) || $nowIn->isBefore($originalIn)) {
                $timesheet->flexible = 1;
            }
        }

        $timesheet->work_status = Timesheet::STATUS_DI_LAM;
        $timesheet->save();

        // Time checking
        TimeChecking::firstOrCreate([
            'datetime' => $logDate . ' ' . $now,
            'client_employee_id' => $this->id,
            'timesheet_id' => $timesheet->id,
            'source' => $source
        ]);
    }

    /**
     * Shorthand for checkTime
     *
     * @param  string  $date
     */
    public function checkOut(string $date = null, string $time = "", int $next_day = -1, string $source = "App")
    {
        $logDate = $date ?? Carbon::now(Constant::TIMESHEET_TIMEZONE)->toDateString();
        return $this->checkTime($logDate, true, $time, $next_day, $source);
    }

    /**
     * @param  \App\Models\WorkScheduleGroup  $workScheduleGroup
     */
    public function refreshTimesheetByWorkScheduleGroup(WorkScheduleGroup $workScheduleGroup, ?array $onlyDates = null)
    {
        $getSchedules = new GetTimesheetSchedules();
        /** @var TimesheetSchedule[]|\Illuminate\Support\Collection $schedules */
        $schedules = $getSchedules->handle($workScheduleGroup->id, $this->id);

        $getTimesheets = new GetTimesheetByWorkScheduleGroup();
        $timesheets = $getTimesheets->handle($workScheduleGroup->id, $this->id);
        $toBeSavedTimesheets = collect();

        // dates that are not work or ot
        $timesheets = $timesheets->keyBy('log_date');
        // $expectedWorkDates = $timesheets-->whereIn('log_date', $workDates)->keyBy('log_date');
        // $otherDates = $timesheets->whereNotIn('log_date', $workDates)->keyBy('log_date');
        foreach ($schedules->groupBy('date') as $date => $scheduleGroup) {
            if ($onlyDates && !in_array($date, $onlyDates)) {
                return;
            }

            /** @var \Illuminate\Support\Collection $scheduleGroup */
            $date = $scheduleGroup->first()->date;
            $timesheet = $timesheets->get($date);
            if (!$timesheet) {
                $timesheet = $this->touchTimesheet($date);
            }

            /** @var TimesheetSchedule $schedule */
            $schedule = $scheduleGroup->first();
            if ($scheduleGroup->groupBy('state')->count() == 1 && $schedule->disabled) {
                // nếu chỉ có một loại state
                // Single state
                // $timesheet->check_in = '00:00';
                // $timesheet->check_out = '00:00';
                if ($schedule->state == 'paid_leave') {
                    // Không cần trạng thái HL nữa
                    $timesheet->work_status = Timesheet::STATUS_NGHI_PHEP_HL;
                } elseif ($schedule->state == 'leave') {
                    // Không cần trạng thái KHL nữa
                    $timesheet->work_status = Timesheet::STATUS_NGHI_PHEP_KHL;
                } elseif ($schedule->state == 'wfh') {
                    // TODO trường hợp một ngày 2 đơn wfh, vẫn còn sai
                    $timesheet->work_status = Timesheet::STATUS_DI_LAM;
                } elseif ($schedule->state == 'outside') {
                    $timesheet->work_status = Timesheet::STATUS_DI_LAM;
                } elseif ($schedule->state == 'off_day') {
                    $timesheet->work_status = Timesheet::STATUS_NGHI_CUOI_TUAN;
                } elseif ($schedule->state == 'holiday') {
                    $timesheet->work_status = Timesheet::STATUS_NGHI_LE;
                } else {
                    $timesheet->work_status = Timesheet::STATUS_DI_LAM;
                }
            } else {
                // nếu có hơn nhiều một loại state
                // $hasWorkSchedule = $scheduleGroup->whereIn('state', ['work', 'ot', 'other'])->count() > 0;
                // if (!$hasWorkSchedule) {
                // if doesn't have any work schedule, let assume work
                // $timesheet->check_in = '';
                // $timesheet->check_out = '';
                // }
                $timesheet->work_status = Timesheet::STATUS_DI_LAM;
            }
            $toBeSavedTimesheets->push($timesheet);
        }

        // DB::transaction(function () use ($toBeSavedTimesheets) {
        foreach ($toBeSavedTimesheets as $timesheet) {
            /** @var \App\Models\Timesheet $timesheet */
            $timesheet->touch();
            $timesheet->save();

            // Timesheet::withoutEvents(function() use ($timesheet) {
            // prevent recalculate run again in observer
            // $timesheet->save();
            // });
        }
        // });
    }

    public function touchTimesheet($logDate): Timesheet
    {
        $timesheet = Timesheet::where("log_date", $logDate)->where('client_employee_id', $this->id)->first();
        if (!$timesheet) {
            $timesheet = new Timesheet();
            $timesheet->client_employee_id = $this->id;
            $timesheet->log_date = $logDate;
            $timesheet->activity = 'activity';
            $timesheet->work_place = 'work_place';
            $timesheet->working_hours = 0;
            $timesheet->overtime_hours = 0;
            $timesheet->check_in = '';
            $timesheet->check_out = '';
            $timesheet->leave_type = 'early_leave';
            $timesheet->attentdant_status = 'doing';
            $timesheet->note = '';
            $timesheet->reason = '';
            $timesheet->work_status = Timesheet::STATUS_DI_LAM;
            $timesheet->save();
        }
        return $timesheet;
    }

    public function touchTimesheetWithoutSaving($logDate): Timesheet
    {
        $timesheet = Timesheet::where("log_date", $logDate)->where('client_employee_id', $this->id)->first();
        if (!$timesheet) {
            $timesheet = new Timesheet();
            $timesheet->client_employee_id = $this->id;
            $timesheet->log_date = $logDate;
            $timesheet->activity = 'activity';
            $timesheet->work_place = 'work_place';
            $timesheet->working_hours = 0;
            $timesheet->overtime_hours = 0;
            $timesheet->check_in = '';
            $timesheet->check_out = '';
            $timesheet->leave_type = 'early_leave';
            $timesheet->attentdant_status = 'doing';
            $timesheet->note = '';
            $timesheet->reason = '';
            $timesheet->work_status = Timesheet::STATUS_DI_LAM;
        }
        return $timesheet;
    }


    /**
     * @return HasMany
     */
    public function worktimeRegister()
    {
        return $this->hasMany(WorktimeRegister::class);
    }

    /**
     * @return HasManyThrough
     */
    public function worktimeRegisterPeriod()
    {
        return $this->hasManyThrough(WorkTimeRegisterPeriod::class, WorkTimeRegister::class);
    }

    public function worktimeRegisterOvertime($filterStart = null, $filterEnd = null)
    {
        $minutes = 0;
        $list = WorktimeRegister::query()
            ->where('client_employee_id', $this->id)
            ->where('type', 'overtime_request')
            ->where('status', 'approved');
        if ($filterStart && $filterEnd) {
            $list->whereDate('start_time', '>=', $filterStart)->whereDate('end_time', '<=', $filterEnd);
        }
        $list = $list->get();
        if ($list->isNotEmpty()) {
            foreach ($list as $item) {
                $durationInMinutes = Carbon::parse($item->end_time)->diffInMinutes(Carbon::parse($item->start_time));
                $minutes += $durationInMinutes;
            }
        }
        return round($minutes / 60, 2);
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("ClientEmployee");
        $attachments = [];

        if (count($media) > 0) {
            foreach ($media as $key => $item) {
                $attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'file_size' => $item->size,
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }

    public function getSalaryAttachmentsAttribute()
    {
        $media = $this->getMedia("salary");
        $salary_attachments = [];

        if (count($media) > 0) {
            foreach ($media as $key => $item) {
                $salary_attachments[] = [
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

        return $salary_attachments;
    }
    /**
     * @param  \App\Models\WorkScheduleGroup  $workScheduleGroup
     * @param  array|null  $onlyDates
     */
    public function refreshTimesheetByWorkScheduleGroupAsync(
        WorkScheduleGroup $workScheduleGroup,
        ?array $onlyDates = null
    ) {
        dispatch(new RefreshClientEmployeeTimesheetJob($this, $workScheduleGroup));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customVariables(): HasMany
    {
        return $this->hasMany('App\Models\ClientEmployeeCustomVariable');
    }

    public function dependentsInformation()
    {
        return $this->hasMany(ClientEmployeeDependent::class);
    }

    public function clientEmployeeLeaveManagement()
    {
        return $this->hasMany(ClientEmployeeLeaveManagement::class);
    }
}
