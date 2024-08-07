<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\HasPdfMedia;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $readable_name
 * @property string $variable_name
 * @property float $variable_value
 * @property string $created_at
 * @property string $updated_at
 * @property ClientEmployee $clientEmployee
 */
class ClientEmployeeContract extends Model implements HasMedia
{

    use InteractsWithMedia;
    use HasPdfMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];
    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'client_employee_contracts';
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
        'client_employee_id', 'contract_type', 'contract_code', 'contract_signing_date', 'contract_end_date',
        'created_at', 'updated_at', 'reminder_date',
    ];

    public function getMediaModel() { return $this->getMedia('ClientEmployeeContract'); }

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
     * @param  ClientEmployeeCustomVariable|Builder  $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->is_internal) {
            $query->whereHas('clientEmployee', function ($clientEmployee) use ($user) {
                $clientEmployee->where('client_id', $user->client_id);
            });
            if ($user->hasPermissionTo('manage-contract')) {
                return $query;
            }
            return $query->where('client_employee_id', $user->clientEmployee->id);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasPermissionTo('manage_clients')) {
                return $query;
            }
            return $query->belongToClientAssignedTo($user->iGlocalEmployee);
        }
    }

    public function getFilePathAttribute(): string
    {
        $media = $this->getMedia('ClientEmployeeContract');

        if (count($media) > 0) {
            return $this->getMediaPathAttribute().'ClientEmployeeContract/'.$media[0]->id.'/'.$media[0]->file_name;
        } else {
            return '';
        }
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ClientEmployeeContract')->singleFile();
        $this->registerPdfCollection();
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
             ->width(50)
             ->height(50)
             ->sharpen(10);
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
}
