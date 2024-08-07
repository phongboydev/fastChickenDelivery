<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property string $id
 * @property string $client_id
 * @property string $first_name
 * @property string $last_name
 * @property string $full_name
 * @property string $email
 * @property string $created_at
 * @property string $updated_at
 */

class CcClientEmail extends Model
{
    use HasFactory, UsesUuid, HasAssignment;

    protected $table = 'cc_client_emails';

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = [
        'email',
        'client_id',
        'first_name',
        'last_name',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
