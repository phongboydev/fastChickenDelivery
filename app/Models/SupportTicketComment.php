<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\SupportTicket;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property string $id
 * @property string $support_ticket_id
 * @property string $user_comment_id
 * @property string $message
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property SupportTicket $supportTicket
 * @property User $user
 */
class SupportTicketComment extends Model implements HasMedia
{
    use Concerns\UsesUuid,InteractsWithMedia, MediaTrait, SoftDeletes, LogsActivity, HasAssignment, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'support_ticket_comments';

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
    protected $fillable = ['support_ticket_id', 'user_comment_id', 'message', 'deleted_at', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function supportTicket()
    {
        return $this->belongsTo('App\Models\SupportTicket', 'support_ticket_id');
    }

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_comment_id');
    }

    public function client()
    {
        return $this->belongsToThrough(Client::class, SupportTicket::class);
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return $query->whereHas('supportTicket', function(SupportTicket $query) {
                        $query->authUserAccessible();
                    });
            }
        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_LEADER:
                case Constant::ROLE_INTERNAL_STAFF:
                    return $query->whereHas('supportTicket', function(SupportTicket $query) {
                        $query->authUserAccessible();
                    });
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return $query;
            }
        }
    }

    public function getMediaModel()
    {
        return $this->getMedia('SupportTicketComment');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("SupportTicketComment");
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
