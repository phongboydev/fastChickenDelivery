<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Znck\Eloquent\Traits\BelongsToThrough;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $no_workpermit
 * @property string $type
 * @property string $form
 * @property string $position
 * @property string $workpermit_created_date
 * @property string $workpermit_expired_date
 * @property ClientEmployee $clientEmployee
 * @property Client $clien
 */
class ClientEmployeeForeignWorkpermit extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_foreign_workpermit';

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
    protected $fillable = ['client_employee_id', 'no_workpermit', 'type', 'form', 'position', 'workpermit_created_date', 'workpermit_expired_date', 'created_at', 'updated_at', 'status', 'approved_by', 'approved_date', 'approved_comment'];

    public function getMediaModel() { return $this->getMedia('ClientEmployeeForeignWorkpermit'); }

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


    public function getFilePathAttribute()
    {
        $media = $this->getMedia('ClientEmployeeForeignWorkpermit');

        if( count($media) > 0 ) {
            return $this->getMediaPathAttribute() . 'ClientEmployeeForeignWorkpermit/' . $media[0]->id . '/' . $media[0]->file_name;
        }else{
            return '';
        }
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
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
