<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\UsesUuid;

class PaymentRequestAmount extends Model
{
    use UsesUuid,
    HasFactory,
    LogsActivity,
    SoftDeletes;

    protected $table = 'payment_requests_amount';

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = ['amount', 'note', 'unit', 'payment_request_id'];

    public function payment()
    {
        return $this->belongsTo('App\Models\PaymentRequest');
    }
}
