<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TODO: table TimeChecking và Checking đang được build song song với mục đích khác nhau.
 * Cần tìm giải pháp chung sau này.
 */

class Checking extends Model
{
    use HasFactory;

    protected $table = 'checking';

    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'client_employee_id',
        'checking_time',
        'source'
    ];
}
