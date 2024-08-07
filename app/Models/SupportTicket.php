<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
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
 * @property User $user
 * @property Client $client
 */
class SupportTicket extends Model implements HasMedia
{
    use Concerns\UsesUuid, SoftDeletes, LogsActivity, HasAssignment, InteractsWithMedia, MediaTrait;

    protected static $logAttributes = ['*'];

    protected $table = 'support_tickets';

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
    protected $fillable = ['client_id', 'user_id', 'assigned', 'subject', 'type_menu_tab', 'status', 'message', 'updater_id', 'deleted_at', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
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
    public function assigned()
    {
        return $this->belongsTo('App\User', 'assigned');
    }

    /**
     * @return HasMany
     */
    public function supportTicketComments()
    {
        return $this->hasMany('App\Models\SupportTicketComment')->orderBy('created_at');
    }

    public function findSupportTicket($id)
    {
        return $this->whereId($id)->firstOrFail()->toArray();
    }

    public function updater() {
        return $this->belongsTo('App\User', 'updater_id');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return $query->where('user_id', '=', $user->id);
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    return $query->belongToClientAssignedTo($user->iGlocalEmployee);
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return $query;
            }
        }
    }

    public function getMediaModel()
    {
        return $this->getMedia('SupportTicket');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("SupportTicket");
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
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10);
    }
}
