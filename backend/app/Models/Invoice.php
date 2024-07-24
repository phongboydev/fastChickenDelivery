<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, UsesUuid, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'id',
        'number',
        'user_id',
        'total_price',
        'issue_date',
        'due_date',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function scopeSearch($query, $q)
    {
        return $query->where('number', 'like', '%' . $q . '%');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSort($query, $sortBy, $orderBy)
    {
        return $query->orderBy($sortBy, $orderBy);
    }

    public function scopePagination($query, $itemsPerPage, $page)
    {
        return $query->paginate($itemsPerPage, ['*'], 'page', $page);
    }

    public function scopeTotalPrice($query, $totalPrice)
    {
        return $query->where('total_price', $totalPrice);
    }

    public function scopeIssueDate($query, $issueDate)
    {
        return $query->where('issue_date', $issueDate);
    }

    public function scopeDueDate($query, $dueDate)
    {
        return $query->where('due_date', $dueDate);
    }

}
