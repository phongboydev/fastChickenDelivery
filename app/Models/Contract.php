<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\HasPdfMedia;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string $contract_type
 * @property string $contract_no
 * @property string $note
 * @property string $status
 * @property string $ma_tham_chieu
 * @property Client $client
 */
class Contract extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, HasAssignment, HasPdfMedia;

    const CONTRACT_MEDIA_COLLECTION = 'Contract';
    const STATUS_WAIT_FOR_COMPANY = "wait_for_company";
    const STATUS_WAIT_FOR_EMPLOYEE = "wait_for_employee";
    const STATUS_WAIT_FOR_SETUP = "wait_for_setup";
    const STATUS_NEW = "new";
    const STATUS_REJECT = "reject";
    const STATUS_DONE = "done";

    protected static $logAttributes = ['*'];

    protected $table = 'contracts';

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
        'client_id',
        'name',
        'contract_type',
        'contract_variables',
        'contract_no',
        'status',
        'ma_tham_chieu',
        'note',
        'client_employee_id',
        'salary_history_id',
        'staff_confirm',
        'staff_comment',
        'staff_confirmed_at',
        'company_signed_at',
        'staff_signed_at',
    ];

    public function getMediaModel()
    {
        return $this->getMedia(self::CONTRACT_MEDIA_COLLECTION);
    }

    public function getContractMedia(): ?Media
    {
        return $this->getFirstMedia(self::CONTRACT_MEDIA_COLLECTION);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50)
            ->sharpen(10);
    }

    public function registerMediaCollections(): void
    {
        $this->registerPdfCollection();
        $this->addMediaCollection(self::CONTRACT_MEDIA_COLLECTION);
    }

    public function getFilePathAttribute()
    {
        $media = $this->getMedia(self::CONTRACT_MEDIA_COLLECTION);

        if (count($media) > 0) {
            return $this->getMediaPathAttribute() . 'Contract/' . $media[0]->id . '/' . $media[0]->file_name;
        } else {
            return '';
        }
    }

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployee::class);
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployeeSalaryHistory()
    {
        return $this->belongsTo(ClientEmployeeSalaryHistory::class, 'salary_history_id');
    }

    /**
     * @return HasMany
     */
    public function contractSignSteps(): HasMany
    {
        return $this->hasMany(ContractSignStep::class);
    }

    public function scopeAuthUserAccessible(Builder $query)
    {
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->is_internal) {
            $query->where('client_id', $user->client_id);
            if ($user->hasPermissionTo('manage-contract')) {
                return $query;
            }
            return $query->where('client_employee_id', $user->clientEmployee->id);
        } else {
            if ($role == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasPermissionTo('manage_clients')) {
                return $query;
            }
            return $query->belongToClientAssignedTo($user->iGlocalEmployee);
        }
    }

    public function getLatestContractMedia(): Media
    {
        $step = $this->contractSignSteps()->whereNotNull("signed_at")
            ->orderBy('signed_at', 'desc')
            ->first();
        if ($step) {
            return $step->getFirstMedia($step->getPdfCollectionName());
        }
        return $this->getFirstMedia($this->getPdfCollectionName());
    }
}
