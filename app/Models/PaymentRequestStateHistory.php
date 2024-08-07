<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\UsesUuid;

class PaymentRequestStateHistory extends Model
{
    use UsesUuid,
    HasFactory,
    LogsActivity,
    SoftDeletes;

    protected $table = 'payment_request_state_history';

    public $timestamps = true;

     /**
     * @var array
     */
    protected $fillable = ['state', 'client_employee_id', 'payment_request_id'];

    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\clientEmployee');
    }
}
