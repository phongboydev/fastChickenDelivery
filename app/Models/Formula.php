<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $name
 * @property string $func_name
 * @property string $parameters
 * @property string $formula
 * @property string $description
 * @property string $deleted_at
 * @property string $created_at
 * @property string $updated_at
 */
class Formula extends Model
{
    use UsesUuid, SoftDeletes, LogsActivity, HasAssignment, HasFactory;

    protected static $logAttributes = ['*'];

    protected $table = 'formulas';

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
        'parent_id', 
        'name', 
        'func_name', 
        'parameters', 
        'formula', 
        'description', 
        'deleted_at', 
        'begin_effective_at',
        'end_effective_at',
        'created_at', 
        'updated_at'];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function scopeAuthUserAccessible($query)
    {
        return $query;
        
        // // Get User from token
        // $user = Auth::user();

        // if (!$user->isInternalUser()) {
        //     $role = $user->getRole();
        //     switch ($role) {
        //         default:
        //             return $query->whereNull('id');
        //     }
        // }
    }
}
