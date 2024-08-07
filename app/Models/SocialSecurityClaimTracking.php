<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\User;
use App\Models\ClientEmployee;
use App\Models\IglocalEmployee;
use App\Models\IglocalAssignment;
use App\Models\CalculationSheet;
use App\Models\Concerns\HasAssignment;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

class SocialSecurityClaimTracking extends Model
{
    use Concerns\UsesUuid;

    protected $table = 'social_security_claim_tracking';

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
        'social_security_claim_id',
        'content'
    ];

    /**
     * @return BelongsTo
     */
    public function socialSecurityClaim()
    {
        return $this->belongsTo('App\Models\SocialSecurityClaim');
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
