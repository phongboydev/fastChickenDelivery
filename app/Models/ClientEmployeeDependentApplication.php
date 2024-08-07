<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\DependentHelper;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ClientEmployeeDependentApplication extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasFactory, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_dependent_applications';

    public $timestamps = true;

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
        'client_id',
        'client_employee_id',
        'creator_id',
        'client_employee_dependent_id',
        'name_dependents',
        'tax_code',
        'identification_number',
        'date_of_birth',
        'nationality',
        'country_code',
        'relationship_code',
        'tax_office_province_id',
        'tax_office_district_id',
        'tax_office_ward_id',
        'dob_info_num',
        'dob_info_book_num',
        'tax_period',
        'reg_type',
        'from_date',
        'to_date',
        'processing',
        'status',
        'internal_note',
        'replicate_id'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'nationality_name',
        'country_name',
        'relationship_name',
        'province_code',
        'province_name',
        'district_code',
        'district_name',
        'ward_code',
        'ward_name'
    ];

    public function getRelationshipNameAttribute()
    {
        return DependentHelper::RELATIONSHIP_NAMES[$this->relationship_code] ?? null;
    }

    public function getNationalityNameAttribute()
    {
        return DependentHelper::NATIONALITY_NAMES[$this->nationality] ?? null;
    }

    public function getCountryNameAttribute()
    {
        return DependentHelper::NATIONALITY_NAMES[$this->country_code] ?? null;
    }

    public function getProvinceCodeAttribute()
    {
        return optional($this->provincialAdministrativeDivision)->administrative_division_code;
    }

    public function getProvinceNameAttribute()
    {
        return optional($this->provincialAdministrativeDivision)->administrative_division;
    }

    public function getDistrictCodeAttribute()
    {
        return optional($this->districtAdministrativeDivision)->administrative_division_code;
    }

    public function getDistrictNameAttribute()
    {
        return optional($this->districtAdministrativeDivision)->administrative_division;
    }

    public function getWardCodeAttribute()
    {
        return optional($this->wardAdministrativeDivision)->administrative_division_code;
    }

    public function getWardNameAttribute()
    {
        return optional($this->wardAdministrativeDivision)->administrative_division;
    }

    public function getMediaModel()
    {
        return $this->getMedia('Attachments');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }

    public function scopeAuthUserAccessible($query)
    {

        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_STAFF:
                case Constant::ROLE_CLIENT_LEADER:
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            return $query;
        }
    }

    public function requests()
    {
        return $this->belongsToMany(ClientEmployeeDependentRequest::class, 'dependent_request_application_links', 'client_employee_dependent_application_id', 'client_employee_dependent_request_id');
    }

    public function dependent()
    {
        return $this->hasOne(ClientEmployeeDependent::class, 'id', 'client_employee_dependent_id');
    }

    public function provincialAdministrativeDivision()
    {
        return $this->belongsTo(TaxOfficeProvince::class, 'tax_office_province_id', 'id');
    }

    public function districtAdministrativeDivision()
    {
        return $this->belongsTo(TaxOfficeDistrict::class, 'tax_office_district_id', 'id');
    }

    public function wardAdministrativeDivision()
    {
        return $this->belongsTo(TaxOfficeWard::class, 'tax_office_ward_id', 'id');
    }

    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployee::class, 'client_employee_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(ClientEmployee::class, 'creator_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(ClientEmployeeDependentApplication::class, 'replicate_id')->withTrashed();
    }

    public function children()
    {
        return $this->hasOne(ClientEmployeeDependentApplication::class, 'replicate_id');
    }
}
