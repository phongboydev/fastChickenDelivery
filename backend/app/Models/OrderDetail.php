<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderDetail extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    protected $table = 'order_details';

    protected $fillable = [
        'order_id',
        'product_by_day_id',
        'quantity',
        'price',
        'total_price',
    ];

    // Set the primary key type to string
    protected $keyType = 'string';

    // Disable auto-incrementing as UUIDs are not integers
    public $incrementing = false;

    public function order(){
        return $this->belongsTo(Order::class);
    }
}
