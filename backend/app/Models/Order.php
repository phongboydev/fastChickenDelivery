<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'id',
        'user_id',
        'order_number',
        'type',
        'order_date',
        'total_price',
        'payment_status',
        'payment_method',
        'payment_date',
        'payment_status',
        ];

    protected $casts = [
        'order_date' => 'date:d-m-Y',
        'payment_date' => 'date:d-m-Y',
        ];

    // Set the primary key type to string
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($order) {
            $order->orderDetails()->delete();
        });
    }

    // Disable auto-incrementing as UUIDs are not integers
    public $incrementing = false;

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function orderDetails(){
        return $this->hasMany(OrderDetail::class);
    }

    public function scopeSearch($query, $q)
    {
        return $query->where('order_number', 'like', "%$q%");
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopeSort($query, $sortBy, $orderBy)
    {
        return $query->orderBy($sortBy, $orderBy);
    }

    public function scopePagination($query, $itemsPerPage, $page)
    {
        return $query->paginate($itemsPerPage, ['*'], 'page', $page);
    }

    public function scopeTotal($query)
    {
        return $query->sum('total_price');
    }

    public function scopeTotalByStatus($query, $status)
    {
        return $query->where('payment_status', $status)->sum('total_price');
    }

    public function scopeTotalByMonth($query, $month)
    {
        return $query->whereMonth('order_date', $month)->sum('total_price');
    }

    public function generateOrderNumber()
    {
        return 'ORD' . strtoupper(uniqid());
    }
}
