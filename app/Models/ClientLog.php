<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasAssignment;

/**
 * @property integer $id
 * @property integer $survey_job_id
 * @property string $log_content
 * @property string $created_at
 * @property string $updated_at
 * @property SurveyJob $surveyJob
 */
class ClientLog extends Model
{
    use HasAssignment;
    /**
     * The "type" of the auto-incrementing ID.
     * 
     * @var string
     */
    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['client_id', 'log_type', 'log_content', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function scopeAuthUserAccessible($query)
    {
        // Get User from token
        $user = Auth::user();

        if( $user->isInternalUser() )
            return $query;

    }

    public function scopeHasInternalAssignment($query)
    {
        if (!Auth::user()->isInternalUser()) {
            return $query->whereNull('id');
        } else {
            return $query->whereHas('assignedInternalEmployees', function(Builder $query) {
                $internalEmployee = new IglocalEmployee();
                $query->where( "{$internalEmployee->getTable()}.id", Auth::user()->iGlocalEmployee->id);
            });
        }
    }
}
