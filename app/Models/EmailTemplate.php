<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_employee_id
 * @property string $salary
 * @property string $title
 * @property string $position
 * @property EmailTemplate $emailTemplate
 */
class EmailTemplate extends Model
{
    use Concerns\UsesUuid, LogsActivity;

    protected $table = 'email_templates';

    public $timestamps = true;

    protected static array $logAttributes = [
        'template_name', 'content_en', 'content_vi', 'content_ja', 'subject_en', 'subject_vi', 'subject_ja', 'created_at', 'updated_at'
    ];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['template_name', 'content_en', 'content_vi', 'content_ja', 'subject_en', 'subject_vi', 'subject_ja', 'created_at', 'updated_at'];

    public function scopeAuthUserAccessible($query)
    {
        return true;
    }
}
