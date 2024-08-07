<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_id
 * @property string $from_date
 * @property string $to_date
 * @property string $created_at
 * @property string $updated_at
 */

class HeadcountPeriodSetting extends Model
{
    use HasFactory, UsesUuid, SoftDeletes, LogsActivity, HasAssignment;

    protected $table = 'headcount_period_settings';

    /**
     * @var array|string[]
     */
    protected static array $logAttributes = ['*'];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $fillable = [
        'id',
        'client_id',
        'from_date',
        'to_date',
        'created_at',
        'updated_at',
    ];

    /**
     * @return HasMany
     */
    public function headcountCostSetting() : HasMany
    {
        return $this->hasMany(HeadcountCostSetting::class, 'headcount_period_setting_id');
    }

    /**
     * @return BelongsTo
     */
    public function client() : BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function forceDeleteHeadcountCostSettingById()
    {
        HeadcountCostSetting::where('headcount_period_setting_id', $this->id)->forceDelete();
    }

    public function deleteHeadcountCostSettingById()
    {
        HeadcountCostSetting::where('headcount_period_setting_id', $this->id)->delete();
    }

    public function scopeAuthUserAccessible($query)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->isInternalUser()) {
            return $query->where('client_id', null);
        } else {
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }
}
