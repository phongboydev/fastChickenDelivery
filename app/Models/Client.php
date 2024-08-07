<?php

namespace App\Models;

use App\Models\ClientEmployee;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\User;
use App\Models\IglocalEmployee;
use App\Models\Province;
use App\Models\ProvinceDistrict;
use App\Models\ProvinceWard;
use App\Models\ClientLog;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Carbon\Carbon;
use Spatie\Translatable\HasTranslations;
use DB;

/**
 * @property string $id
 * @property string $code
 * @property string $company_name
 * @property string $address
 * @property string $company_bank_account
 * @property string $company_account_number
 * @property string $company_bank_name
 * @property string $company_bank_branch
 * @property string $person_signing_a_bank_document
 * @property int $employees_number_foreign
 * @property int $employees_number_vietnamese
 * @property boolean $rewards_for_achievements
 * @property boolean $annual_salary_bonus
 * @property string $social_insurance_and_health_insurance_ceiling
 * @property string $unemployment_insurance_ceiling
 * @property string $payroll_creator
 * @property string $payroll_approver
 * @property int $timesheet_min_time_block
 * @property int $ot_min_time_block
 * @property string $social_insurance_agency
 * @property string $social_insurance_account_name
 * @property string $social_insurance_account_number
 * @property string $social_insurance_bank_name
 * @property string $social_insurance_bank_branch
 * @property string $trade_union_agency
 * @property string $trade_union_account_name
 * @property string $trade_union_account_number
 * @property string $trade_union_bank_name
 * @property string $trade_union_bank_branch
 * @property string $base_union_branch_name
 * @property string $base_union_bank_name
 * @property string $base_union_account_number
 * @property string $base_union_account_name
 * @property string $base_union_name
 * @property boolean $enable_behalf_service
 * @property boolean $enable_behalf_service_company
 * @property string $pit_declaration_the_controlling_agency
 * @property string $pit_declaration_state_budget_account
 * @property string $pit_declaration_chapter
 * @property string $pit_declaration_company_tax_code
 * @property string $pit_declaration_at_the_state_treasury
 * @property string $pit_declaration_province
 * @property string $pit_declaration_head
 * @property string $behalf_service_information_id
 * @property string $behalf_service_information_company_id
 * @property string $created_at
 * @property string $updated_at
 * @property string $headquarters_country_code
 * @property ClientWorkflowSetting $clientWorkflowSetting
 * @property PaymentOnBehalfServiceInformation $paymentOnBehalfServiceInformation
 */
class Client extends Model
{
    use Concerns\UsesUuid, SoftDeletes, LogsActivity, HasTranslations;

    protected static $logAttributes = ['*'];

    protected $table = 'clients';

    public $timestamps = true;

    public $translatable = ['company_name', 'company_abbreviation', 'address', 'base_union_name'];

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

    /**
     * @var array
     */
    protected $fillable = [
        'code',
        'company_name',
        'company_abbreviation',
        'company_contact_phone',
        'company_contact_email',
        'address',
        'address_province',
        'address_city',
        'address_province_id',
        'address_province_district_id',
        'address_province_ward',
        'address_province_ward_id',
        'company_bank_account',
        'company_account_number',
        'company_bank_name',
        'company_bank_branch',
        'person_signing_a_bank_document',
        'employees_number_foreign',
        'employees_number_vietnamese',
        'rewards_for_achievements',
        'annual_salary_bonus',
        'social_insurance_and_health_insurance_ceiling',
        'unemployment_insurance_ceiling',
        'payroll_creator',
        'payroll_approver',
        'social_insurance_agency',
        'social_insurance_account_name',
        'social_insurance_account_number',
        'social_insurance_bank_name',
        'social_insurance_bank_branch',
        'social_insurance_address',
        'social_insurance_city_province',
        'social_insurance_district',
        'social_insurance_wards',
        'trade_union_agency',
        'trade_union_account_name',
        'trade_union_account_number',
        'trade_union_bank_name',
        'trade_union_bank_branch',
        'base_union_branch_name',
        'base_union_bank_name',
        'base_union_account_number',
        'base_union_account_name',
        'base_union_name',
        'enable_behalf_service',
        'enable_behalf_service_company',
        'pit_declaration_the_controlling_agency',
        'pit_declaration_state_budget_account',
        'pit_declaration_chapter',
        'pit_declaration_company_tax_code',
        'pit_declaration_at_the_state_treasury',
        'pit_declaration_province',
        'pit_declaration_province_id',
        'pit_declaration_district_id',
        'pit_declaration_head',
        'behalf_service_information_id',
        'behalf_service_information_company_id',
        'presenter_phone',
        'company_contact_fax',
        'presenter_email',
        'presenter_name',
        'company_license_no',
        'company_license_issuer',
        'company_license_issued_at',
        'company_license_updated_at',
        'company_license_at',
        'timesheet_min_time_block',
        'ot_min_time_block',
        'type_of_business',
        'is_active',
        'standard_work_hours_per_day',
        'client_type',
        'is_test',
        'seniority_contract_type',
        'federation_of_labor_id',
        'social_security_bank_id',
        'trade_union_bank_id',
        'base_union_bank_id',
        'company_bank_id',
        'headquarters_country_code'
    ];


    public function getCountIsInvolvedPayrollAttribute()
    {
        $countIsInvolvedPayroll = 0;
        if ($this->client_type == 'system') {
            $listClientEmployee = $this->clientEmployees->where("status", Constant::CLIENT_EMPLOYEE_STATUS_WORKING);
        } else {
            $listClientEmployee = ClientEmployee::where([
                'client_id' => $this->id,
                'is_involved_payroll' => 1,
                'status' => Constant::CLIENT_EMPLOYEE_STATUS_WORKING
            ])->get();
        }

        if (!empty($listClientEmployee)) {
            $countIsInvolvedPayroll = count($listClientEmployee);
        }
        return $countIsInvolvedPayroll;
    }

    public function getTaxOfficeProvinceCodeAttribute()
    {
        return optional($this->taxOfficeByProvince)->tax_office_code;
    }

    public function getTaxOfficeProvinceNameAttribute()
    {
        return optional($this->taxOfficeByProvince)->tax_office;
    }

    public function getTaxOfficeDistrictCodeAttribute()
    {
        return optional($this->taxOfficeByDistrict)->tax_office_code;
    }

    public function getTaxOfficeDistrictNameWithCodeAttribute()
    {
        return optional($this->taxOfficeByDistrict)->tax_office_name_with_code;
    }

    public function getTaxOfficeDistrictNameAttribute()
    {
        return optional($this->taxOfficeByDistrict)->tax_office;
    }

    public function getPitDeclarationProvinceCodeAttribute()
    {
        return optional($this->taxOfficeByProvince)->administrative_division_code;
    }

    public function getPitDeclarationProvinceNameAttribute()
    {
        return optional($this->taxOfficeByProvince)->administrative_division;
    }

    public function getPitDeclarationDistrictCodeAttribute()
    {
        return optional($this->taxOfficeByDistrict)->administrative_division_code;
    }

    public function getPitDeclarationDistrictNameAttribute()
    {
        return optional($this->taxOfficeByDistrict)->administrative_division;
    }

    public function getSocialInsuranceFullAddressAttribute()
    {
        if($this->social_insurance_city_province && $this->social_insurance_district && $this->social_insurance_wards)
        {
            $province = Province::select('province_name')->where('id', $this->social_insurance_city_province)->first();
            $district = ProvinceDistrict::select('district_name')->where('id', $this->social_insurance_district)->first();
            $wards    = ProvinceWard::select('ward_name')->where('id', $this->social_insurance_wards)->first();

            if($province && $district && $wards) {
                return join(', ', [$this->social_insurance_address, $wards->ward_name, $district->district_name, $province->province_name]);
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    /**
     * @return HasMany
     */
    public function user()
    {
        return $this->hasMany('App\User');
    }

    /**
     * @return HasMany
     */
    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * @return HasMany
     */
    public function workScheduleGroups()
    {
        return $this->hasMany(WorkScheduleGroup::class);
    }

    /**
     * @return HasMany
     */
    public function assignments()
    {
        return $this->hasMany('App\Models\IglocalAssignment');
    }

    /**
     * Internal employees who assigned to this client
     * @return BelongsToMany
     */
    public function assignedInternalEmployees()
    {
        return $this->belongsToMany(IglocalEmployee::class, 'iglocal_assignments');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentOnBehalfServiceInformation()
    {
        return $this->belongsTo(PaymentOnBehalfServiceInformation::class, 'behalf_service_information_id');
    }

    /**
     * @return HasMany
     */
    public function clientUnitCode(): HasMany
    {
        return $this->hasMany(ClientUnitCode::class);
    }

    /**
     * @return HasMany
     */
    public function ccClientEmail(): HasMany
    {
        return $this->hasMany(CcClientEmail::class);
    }

    public function approveFlow(): HasMany
    {
        return $this->hasMany(ApproveFlow::class);
    }

    /**
     * @return HasMany
     */
    public function calculationSheet()
    {
        return $this->hasMany('App\Models\CalculationSheet');
    }

    /**
     * @return HasMany
     */
    public function workScheduleGroupTemplates()
    {
        return $this->hasMany('App\Models\WorkScheduleGroupTemplate');
    }

    /**
     * @return HasMany
     */
    public function clientEmployeeGroup()
    {
        return $this->hasMany('App\Models\ClientEmployeeGroup');
    }

    public function clientEmployeeSalaryHistory()
    {
        return $this->hasManyThrough(
            ClientEmployeeSalaryHistory::class,
            ClientEmployee::class,
            'client_id',
            'client_employee_id',
            'id',
            'id'
        );
    }

    public function socialSecurityBank()
    {
        return $this->hasOne(ProvinceBank::class, 'id', 'social_security_bank_id');
    }

    public function companyBank()
    {
        return $this->hasOne(ProvinceBank::class, 'id', 'company_bank_id');
    }

    public function tradeUnionBank()
    {
        return $this->hasOne(ProvinceBank::class, 'id', 'trade_union_bank_id');
    }

    public function baseUnionBank()
    {
        return $this->hasOne(ProvinceBank::class, 'id', 'base_union_bank_id');
    }

    public function federationOfLabor()
    {
        return $this->hasOne(FederationOfLabor::class, 'id', 'federation_of_labor_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function clientWorkflowSetting(): HasOne
    {
        return $this->hasOne(ClientWorkflowSetting::class);
    }

    /**
     * Get the debitSetup associated with the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function debitSetup()
    {
        return $this->hasOne(DebitSetup::class);
    }

    /**
     * Get all of the debitRequest for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function debitRequests()
    {
        return $this->hasMany(DebitRequest::class);
    }

    /**
     * Get all of the clientEmployees for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clientEmployees()
    {
        return $this->hasMany(ClientEmployee::class);
    }

    /**
     * Get all of the HeadcountPeriodSetting for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function headcountPeriodSetting()
    {
        return $this->hasMany(HeadcountPeriodSetting::class);
    }

    /**
     * Get the assingmentProject associated with the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function assignmentProject()
    {
        return $this->hasOne(AssignmentProject::class, 'client_id', 'id');
    }

    /**
     * @param string $logContent
     *
     * @return bool
     */
    public function addLog($logType = "", $logContent = "")
    {
        $now = Carbon::now();
        $log = new ClientLog();
        $log->fill([
            "log_type" => $logType,
            "log_content" =>  "[" . $now->toDateTimeString() . "] " . $logContent,
        ]);
        $log->client_id = $this->id;
        return $log->save();
    }

    /**
     * @param $query
     * @param $internalEmployeeId
     *
     * @return mixed
     */
    public function scopeAssignedTo($query, $internalEmployeeId)
    {
        $query->whereHas('assignedInternalEmployees', function (Builder $query) use ($internalEmployeeId) {
            $internalEmployee = new IglocalEmployee();
            $query->where("{$internalEmployee->getTable()}.id", $internalEmployeeId);
        });
        return $query;
    }

    public function scopeHasInternalAssignment($query)
    {
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->whereNull('id');
        } else {

            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInActive($query)
    {
        return $query->where('is_active', false);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                default:
                    return $query->where('id', '=', $user->client_id);
            }
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->assignedTo($user->iGlocalEmployee->id);
            }
        }
    }

    protected static function boot()
    {
        parent::boot();
        $user = auth()->user();
        if ($user) {
            $lang = $user->prefered_language ?? app()->getLocale();
            app()->setLocale($lang);
        }
    }

    public function getCompanyNameTranslationsAttribute()
    {
        $translations = $this->getTranslations('company_name');
        return $translations;
    }

    public function training_senimars()
    {
        return $this->hasMany(TrainingSeminar::class);
    }

    public function taxOfficeByProvince()
    {
        return $this->hasOne(TaxOfficeProvince::class, 'id', 'pit_declaration_province_id');
    }

    public function taxOfficeByDistrict()
    {
        return $this->hasOne(TaxOfficeDistrict::class, 'id', 'pit_declaration_district_id');
    }

    /**
     * Get all of the clientHeadCountHistory for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function clientHeadCountHistory()
    {
        return $this->hasMany(ClientHeadCountHistory::class);
    }
}
