<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $client_id
 * @property string $presenter_name_on_behalf
 * @property string $code
 * @property string $type
 * @property string $created_at
 * @property string $updated_at
 */

class ClientUnitCode extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    protected $table = 'client_unit_codes';

    public const UNIT_TYPE = [
        'vietnamese'                     => 0,
        'foreigner'                      => 1,
        'vietnamese_working_abroad'      => 2,
        'vietnamese_outside_working_age' => 3
        ];

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'client_id',
        'code',
        'type',
        'created_at',
        'updated_at',
    ];

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
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            return $query;
        }
    }
}
