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
use Znck\Eloquent\Traits\BelongsToThrough;

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
class ClientDepartmentEmployee extends Model
{
    use UsesUuid, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'client_department_employees';

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
    protected $fillable = ['id', 'client_id', 'client_department_id', 'client_employee_id', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsToThrough(Client::class, ClientDepartment::class);
    }

    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
