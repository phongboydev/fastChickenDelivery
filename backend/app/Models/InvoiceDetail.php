<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDetail extends Model
{
    use HasFactory, SoftDeletes, UsesUuid;

    protected $table = 'invoice_details';

    protected $fillable = [
        'id',
        'invoice_id',
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'product_quantity',
        'product_discount',
        'product_total',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
