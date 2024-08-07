<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentProcessTemplate extends Model
{
    use Concerns\UsesUuid; // Assuming this trait exists in your application
    public $timestamps = true;
    protected $table = 'recruitment_process_template';

    protected $fillable = [
        'name', 'client_id', 'configuration', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'configuration' => 'array',
    ];



}
