<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class EvaluationObject extends Model
{
    use UsesUuid;
    use SoftDeletes;

    protected $table = 'evaluation_object';

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
        'client_employee_id',
        'evaluation_group_id',
        'step',
        'total_steps',
        'assigned_step',
        'assigned_evaluator_id'
    ];

    public function evaluationGroup()
    {
        return $this->belongsTo(EvaluationGroup::class, 'evaluation_group_id');
    }

    public function clientEmployee(){
        return $this->belongsTo(ClientEmployee::class, 'client_employee_id', 'id');
    }

    public function evaluationParticipants():HasMany{
        return $this->hasMany(EvaluationParticipant::class, 'evaluation_object_id', 'id');
    }

    public function assignedEvaluator(){
        return $this->belongsTo(ClientEmployee::class, 'assigned_evaluator_id', 'id');
    }

    public function scopeAuthUserAccessible($query)
    {
         // Get User from token
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user->isInternalUser()) {
            return $query->where($this->getTable() . '.client_id', '=', $user->client_id);
        } else {
            return $query;
        }
    }
}
