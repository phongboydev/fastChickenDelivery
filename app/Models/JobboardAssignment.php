<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasAssignment;
use Illuminate\Support\Facades\Auth;
use Znck\Eloquent\Traits\BelongsToThrough;
use App\User;

/**
 * @property string $id
 * @property string $client_id
 * @property string $jobboard_job_id
 * @property string $appliant_name
 * @property string $appliant_tel
 * @property string $appliant_email
 * @property string $cover_letter
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property Client $client
 */
class JobboardAssignment extends Model
{
    use UsesUuid, HasAssignment, BelongsToThrough, SoftDeletes;

    protected $table = 'jobboard_assignments';

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
        'jobboard_job_id',
        'client_employee_id',
        'created_at',
        'updated_at'
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @return BelongsTo
     */
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function user()
    {
        return $this->belongsToThrough(User::class, ClientEmployee::class);
    }

    /**
     * @return BelongsTo
     */
    public function jobboardJob()
    {
        return $this->belongsTo('App\Models\JobboardJob');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        /** @var User */
        $user = Auth::user();

        if (!$user->isInternalUser()) {
            $role = $user->getRole();
            switch ($role) {
                default:
                    return $query->where('client_id', '=', $user->client_id);
            }
        } else {
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
