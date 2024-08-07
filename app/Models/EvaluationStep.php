<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class EvaluationStep extends Model
{
    use HasFactory;
    use UsesUuid, LogsActivity, SoftDeletes;
    protected static $logAttributes = ['*'];

    protected $table = 'evaluation_steps';

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
        'title', 
        'step', 
        'isSelf', 
        'deadline_date', 
        'evaluation_group_id', 
    ];

    protected $cast = [
        'deadline_date' => 'date'
    ];

    public function evaluationGroup(): BelongsTo{
        return $this->belongsTo(EvaluationGroup::class);
    }

    public function clientEmployees(){
        return $this->belongsToMany(ClientEmployee::class, 'step_evaluator', 'evaluation_step_id', 'evaluator_id');
    }

    public function evaluationParticipants():HasMany
    {
        return $this->hasMany(EvaluationParticipant::class, 'evaluation_step_id', 'id');
    }

    protected static function booted()
    {
        static::addGlobalScope('sortByStep', function (Builder $builder) {
            $builder->orderBy('step', 'ASC');
        });
    }
    
}
