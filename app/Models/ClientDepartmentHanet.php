<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\UsesUuid;

class ClientDepartmentHanet extends Model
{
    use HasFactory;
    use UsesUuid, LogsActivity;

    protected static $logAttributes = ['*'];

    protected $table = 'client_department_hanet';

    public $timestamps = true;    

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';
    
        /**
     * @var array
     */
    protected $fillable = [
        'client_department_id',
        'hanet_department_id',
        'hanet_place_id',
        'name',
        'desc'
    ];
}
