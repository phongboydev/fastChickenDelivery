<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class ClientTeacherTraining extends Model
{
    use HasFactory, UsesUuid;
    protected $table = 'client_teacher_training';
    public $timestamps = true;
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'id', 
        'name',
        'code',
        'description',
        'client_id'    
    ];

    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }
}
