<?php

namespace App\Models;

use App\Casts\PriceCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductByDay extends Model
{
    use HasFactory, Concerns\UsesUuid;

    protected $table = 'product_by_days';

    protected $fillable = [
        'id',
        'date',
        'product_id',
        'price',
        'status',
    ];

    protected $casts = [
        'price' => PriceCast::class,
    ];

    // Set the primary key type to string
    protected $keyType = 'string';

    // Disable auto-incrementing as UUIDs are not integers
    public $incrementing = false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeSearch($query, $q)
    {
        return $query->whereHas('product', function($query) use ($q) {
            $query->where('name', 'like', "%$q%");
        });
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeProduct($query, $product)
    {
        return $query->where('product_id', $product);
    }

    public function scopeSort($query, $sortBy, $orderBy)
    {
        return $query->orderBy($sortBy, $orderBy);
    }

    public function scopePagination($query, $itemsPerPage, $page)
    {
        return $query->paginate($itemsPerPage, ['*'], 'page', $page);
    }
}
