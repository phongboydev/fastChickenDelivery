<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckingBackup extends Model
{
    use HasFactory;

    protected $table = 'checking_backup';

    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'date',
        'data',
        'created_at',
        'updated_at'
    ];
}
