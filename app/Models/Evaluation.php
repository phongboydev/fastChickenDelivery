<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Znck\Eloquent\Traits\BelongsToThrough;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $client_id
 * @property string $rating_group_id
 * @property string $scoreboard
 * @property int $score
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property RatingGroup $rating_group
 */
class Evaluation extends Model
{
    use UsesUuid, LogsActivity, BelongsToThrough;

    protected static $logAttributes = ['*'];

    protected $table = 'evaluations';

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
        'client_employee_id',
        'evaluator_list_id',
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
        return $this->belongsTo(ClientEmployee::class);
    }

    public function evaluationGroup()
    {
        return $this->belongsTo('App\Models\EvaluationGroup');
    }
    public function evaluationUsers()
    {
        return $this->hasMany(EvaluationUser::class);
    }
}
