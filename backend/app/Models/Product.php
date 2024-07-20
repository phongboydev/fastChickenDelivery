<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'description',
        'status',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeSearch($query, $q)
    {
        return $query->where('name', 'like', '%'.$q.'%');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category_id', $category);
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
