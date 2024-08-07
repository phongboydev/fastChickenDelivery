<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @property string $id
 * @property string $type
 * @property string $notifiable_id
 * @property string $notifiable_type
 * @property string $data
 * @property string $read_at
 * @property string $created_at
 * @property string $updated_at
 */
class Notification extends DatabaseNotification
{

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'string',
    ];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        return $query->where('notifiable_id', '=', $user->id);
    }
}
