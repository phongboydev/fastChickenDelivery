<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use Znck\Eloquent\Traits\BelongsToThrough;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $name_dependents
 * @property string $tax_code
 * @property float $relationship
 * @property float $from_date
 * @property string $created_at
 * @property string $updated_at
 * @property ClientEmployee $clientEmployee
 */
class ClientEmployeeDependent extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_dependents';

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
        'client_employee_id',
        'name_dependents',
        'tax_code',
        'relationship',
        'from_date',
        'to_date',
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
        'tax_period'
    ];

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

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientEmployee::class);
    }

    /**
     * @param ClientEmployeeCustomVariable|Builder $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            return  $query->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
                $clientEmployee->where('client_id', $user->client_id);
            });
        } else {
            return $query;
        }
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

    public function applications()
    {
        return $this->hasOne(ClientEmployeeDependentApplication::class);
    }

    public function approves(): MorphMany
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }
}
