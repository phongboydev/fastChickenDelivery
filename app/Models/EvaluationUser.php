<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\MediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Znck\Eloquent\Traits\BelongsToThrough;
use Spatie\Activitylog\Traits\LogsActivity;

class EvaluationUser extends Model implements HasMedia
{
    use InteractsWithMedia;
    use MediaTrait;
    use UsesUuid, LogsActivity, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'evaluation_users';

    public $timestamps = true;

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
    protected $fillable = [
        'client_id',
        'evaluation_group_id',
        'evaluator_id',
        'client_employee_id',
        'evaluation_id',
        'scoreboard',
        'score',
        'step_id',
    ];

    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsToThrough(Client::class, EvaluationGroup::class);
    }
    public function clientEmployee()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function clientEmployeeForValuer()
    {
        return $this->belongsTo('App\Models\ClientEmployee', 'evaluator_id' , 'id');
    }
    public function evaluator()
    {
        return $this->belongsTo('App\Models\ClientEmployee');
    }

    public function getMediaModel()
    {
        return $this->getMedia('EvaluationUser');
    }

    public function getAttachmentsAttribute()
    {
        $media = $this->getMedia("EvaluationUser");
        $attachments = [];
        if (count($media) > 0) {
            foreach ($media as $key => $item) {
                $attachments[] = [
                    'path' => $this->getPublicTemporaryUrl($item),
                    'id' => $item->id,
                    'file_name' => $item->file_name,
                    'name' => $item->name,
                    'mime_type' => $item->mime_type,
                    'collection_name' => $item->collection_name,
                    'created_at' => $item->created_at,
                    'human_readable_size' => $item->human_readable_size,
                    'url' => $this->getPublicTemporaryUrl($item),
                ];
            }
        }

        return $attachments;
    }
}
