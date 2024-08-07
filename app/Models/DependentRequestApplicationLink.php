<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;
use Spatie\Activitylog\Traits\LogsActivity;

class DependentRequestApplicationLink extends Model
{
    use UsesUuid, LogsActivity, HasFactory;

    protected static $logAttributes = ['*'];

    protected $table = 'dependent_request_application_links';

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
    protected $fillable = ['client_employee_dependent_request_id', 'client_employee_dependent_application_id'];

    public function clientEmployeeDependentRequest()
    {
        return $this->belongsTo(ClientEmployeeDependentRequest::class, 'client_employee_dependent_request_id');
    }

    public function clientEmployeeDependentApplication()
    {
        return $this->belongsTo(ClientEmployeeDependentApplication::class, 'client_employee_dependent_application_id');
    }
}
