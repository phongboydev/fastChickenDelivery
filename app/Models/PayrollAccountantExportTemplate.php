<?php

namespace App\Models;

use App\Models\ClientWorkflowSetting;
use App\Models\Concerns\UsesUuid;
use App\Models\Concerns\HasAssignment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;
use App\Support\MediaTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PayrollAccountantExportTemplate extends Model implements HasMedia
{
    public const MEDIA_COLLECTION = "PayrollAccountantExportTemplate";
    use Concerns\UsesUuid, InteractsWithMedia, MediaTrait, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'payroll_accountant_export_templates';

    public $timestamps = false;

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

    public function getMediaModel()
    {
        return $this->getMedia(self::MEDIA_COLLECTION);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'client_id',
        'template_variables',
    ];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

            if ($clientWorkflowSetting && $clientWorkflowSetting->enable_create_payroll && $user->hasDirectPermission('manage-payroll')) {
                return $query->where("{$this->getTable()}.client_id", $user->client_id);
            } else {
                return $query->whereNull('id');
            }
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return $query;
            } else {
                return $query->belongToClientAssignedTo($user->iGlocalEmployee);
            }
        }
    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function (Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where("{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }

    /**
     * @return BelongsTo
     */
    public function payrollAccountantTemplate(): BelongsTo
    {
        return $this->belongsTo(PayrollAccountantTemplate::class, "template_variables");
    }
}
