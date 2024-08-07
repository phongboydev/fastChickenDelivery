<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Models\Concerns\UsesUuid;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\Support\Constant;

/**
 * @property string $id
 * @property string $client_id
 * @property string $readable_name
 * @property string $variable_name
 * @property string $scope
 * @property float $variable_value
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 */
class ClientDepartment extends Model
{
    use UsesUuid;

    protected static $logAttributes = ['*'];

    protected $table = 'client_departments';

    protected $guarded = [];

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
    protected $fillable = ['id', 'client_id', 'no', 'code', 'department', 'position', 'parent_id', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function clientDepartmentEmployees()
    {
        return $this->hasMany(ClientDepartmentEmployee::class)->limit(3);
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
