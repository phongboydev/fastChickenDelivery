<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Auth;
use App\User;

class EvaluationParticipant extends Model
{
    use HasFactory;
    use UsesUuid, LogsActivity, SoftDeletes;
    protected static $logAttributes = ['*'];

    protected $table = 'evaluation_participants';

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
        'evaluation_object_id', 
        'evaluation_step_id', 
        'scoreboard', 
        'created_by',
        'updated_by',
        'lock',
        'is_skiped',
        'evaluation_date'
    ];

    protected $cast = [];

    public function evaluationStep(): BelongsTo
    {
        return $this->belongsTo(EvaluationStep::class, "evaluation_step_id");
    }

    public function clientEmployee(){
        return $this->belongsTo(ClientEmployee::class, 'client_employee_id', 'id');
    }

    public function evaluationObject(){
        return $this->belongsTo(EvaluationObject::class, 'evaluation_object_id', 'id');
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater(){
        return $this->belongsTo(User::class, 'updated_by', 'id');
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
