<?php

namespace App\Models;

use App\Models\Concerns\HasAssignment;
use App\Support\Constant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property string $id
 * @property string $client_id
 * @property int $step
 * @property string $flow_name
 * @property string $created_at
 * @property string $updated_at
 * @property Client $client
 * @property ApproveFlowsUser[] $approveFlowsUsers
 */
class ApproveGroup extends Model
{

    use UsesUuid;

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
    protected $fillable = ['client_id', 'type', 'content', 'created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeAuthUserAccessible($query)
    {
        return $query;
    }
}
