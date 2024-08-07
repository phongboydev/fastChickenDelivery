<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ClientEmployeeDependentRequest extends Model
{
    use UsesUuid, LogsActivity, HasFactory, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'client_employee_dependent_requests';

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
        'code',
        'type',
        'creator_id',
        'processor_id',
        'client_note',
        'internal_note',
        'processing',
        'status'
    ];

    public function scopeAuthUserAccessible($query)
    {

        // Get User from token
        /** @var User $user */
        $user = Auth::user();
        $role = $user->getRole();

        if (!$user->isInternalUser()) {
            switch ($role) {
                case Constant::ROLE_CLIENT_STAFF:
                case Constant::ROLE_CLIENT_LEADER:
                case Constant::ROLE_CLIENT_ACCOUNTANT:
                case Constant::ROLE_CLIENT_MANAGER:
                case Constant::ROLE_CLIENT_HR:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            return $query;
        }
    }

    public function applications()
    {
        return $this->belongsToMany(ClientEmployeeDependentApplication::class, 'dependent_request_application_links', 'client_employee_dependent_request_id', 'client_employee_dependent_application_id')->withTrashed();
    }

    public function link()
    {
        return $this->hasMany(DependentRequestApplicationLink::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo('App\User', 'creator_id');
    }

    public function processor()
    {
        return $this->belongsTo('App\User', 'processor_id');
    }
}
