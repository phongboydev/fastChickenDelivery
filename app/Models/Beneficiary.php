<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Beneficiary extends Model
{
    use UsesUuid, HasFactory, LogsActivity, SoftDeletes;
    protected $table = 'beneficiaries';

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
        'client_employee_id',
        'beneficiary_name',
        'beneficiary_bank',
        'beneficiary_account_number'
    ];

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_employee_id', '=', $user->clientEmployee->id);
        } else {
            return $query;
        }
    }

    public function clientEmployees()
    {
        return $this->hasOne(ClientEmployee::class);
    }
}
