<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PaymentRequest extends Model implements HasMedia
{
    use UsesUuid;
    use InteractsWithMedia;
    use MediaTrait;

    protected $fillable = array(
        'client_id',
        'client_employee_id',
        'supplier_id',
        'business_trip_id',
        'title',
        'total_amount',
        'note',
        'state',
        'category',
        'unit',
        'type',
        'amount',
        'status',
        'approved_comment',
        'code'
    );

    public function getMediaModel()
    {
        return $this->getMedia('PaymentRequest');
    }

    public function approves()
    {
        return $this->morphMany('App\Models\Approve', 'target');
    }

    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function businessTrip()
    {
        return $this->belongsTo(WorktimeRegister::class);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('images')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }

    protected static function boot()
    {
        parent::boot();
        static::deleted(function ($paymentRequest) {
            $paymentRequest->approves()->delete();
        });
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = auth()->user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
                default:
                    if ($user->hasPermissionTo("manage-payment-request")) {
                        return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
                    } else {
                        return $query->where($this->getTable() . '.client_employee_id', '=', $user->clientEmployee->id);
                    }
            }
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function amounts()
    {
        return $this->hasMany('App\Models\PaymentRequestAmount');
    }

    public function stateHistory()
    {
        return $this->hasMany('App\Models\PaymentRequestStateHistory');
    }
}
