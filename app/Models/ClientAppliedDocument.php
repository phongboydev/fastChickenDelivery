<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAppliedDocument extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use Concerns\UsesUuid, LogsActivity, HasAssignment, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'client_applied_documents';

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
        'user_id',
        'code',
        'document_type',
        'status',
        'description',
        'detail',
        'created_at',
        'updated_at'
    ];

    public function getMediaModel() { return $this->getMedia('ClientAppliedDocument'); }

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


    public function clientNotificationUsers()
    {
        return $this->belongsToMany('App\User', 'client_applied_document_user');
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function ccClientEmails()
    {
        return $this->belongsToMany(CcClientEmail::class, 'client_applied_document_cc_client_email');
    }

    /**
     * Get all of the post's comments.
     */
    public function comments()
    {
        return $this->morphMany('App\Models\Comment', 'target');
    }

    /**
     * Get the category that owns the ClientAppliedDocument
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(AppliedDocumentCategory::class, 'document_type', 'id');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            if ($user->checkHavePermission(['permission_apply_document'], [])) {
                return $query->where('client_id', '=', $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            }else{
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
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
