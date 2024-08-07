<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code
 * @property string $name
 * @property string $role
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 * @property User $user
 * @property IglocalAssignment[] $iglocalAssignments
 */
class OauthAutoToken extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'oauth_auto_tokens';

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
    protected $fillable = ['user_id', 'token', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function scopeAuthUserAccessible($query)
    {
        return true;
    }
}
