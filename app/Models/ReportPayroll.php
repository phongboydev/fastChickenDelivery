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
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
class ReportPayroll extends Model implements HasMedia
{
    use InteractsWithMedia, MediaTrait;
    use Concerns\UsesUuid;

    protected $table = 'report_payrolls';

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
        'date_from',
        'date_to',
        'status',
        'original_creator_id',
        'created_at',
        'updated_at'
    ];

    public function getMediaModel() { return $this->getMedia('ReportPayroll'); }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if($user->isInternalUser()) return $query;
    }

}
