<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PaymentRequestExportTemplate extends Model implements HasMedia
{
    use InteractsWithMedia, UsesUuid, MediaTrait, HasAssignment, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'payment_request_export_templates';

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

    public function getMediaModel()
    {
        return $this->getMedia('PaymentRequestExportTemplate');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @var array
     */
    protected $fillable = ['name', 'client_id'];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            return $query->where("{$this->getTable()}.client_id", $user->client_id);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
