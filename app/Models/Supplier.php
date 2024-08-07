<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\UsesUuid;

class Supplier extends Model
{
    use UsesUuid, HasFactory, LogsActivity, SoftDeletes;

    protected static $logAttributes = ['*'];

    protected $table = 'suppliers';

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = ['name', 'client_id','client_employee_id', 'beneficiary_id', 'bank_name', 'bank_account_number', 'bank_account_owner_name',  'address', 'description'];

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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class, 'beneficiary_id');
    }
}
