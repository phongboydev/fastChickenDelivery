<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HasAssignment;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\JobboardApplication;

/**
 * @property string $id
 * @property string $client_id
 * @property string $title
 * @property string $rank
 * @property string $position
 * @property string $job_details
 * @property string $job_requirements
 * @property string $location
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 * @property Client $client
 */
class JobboardJob extends Model
{
    use UsesUuid, SoftDeletes, LogsActivity, HasAssignment;

    protected static $logAttributes = ['*'];

    protected $table = 'jobboard_jobs';

    public $timestamps = true;
    protected $dates = ['deleted_at'];

    protected $casts = [
        'salary_range' => 'array',
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

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($jobboardJob) {
            $jobboardJob->jobboardApplications()->delete();
            $jobboardJob->jobboardAssignments()->delete();
        });
    }
    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'welfare_regime',
        'position',
        'job_details',
        'job_requirements',
        'deleted_at',
        'is_active',
        'salary_range',
        'expired_at',
        'location',
    ];

    public function jobboardApplications()
    {
        return $this->hasMany(JobboardApplication::class);
    }

    public function jobboardAssignments()
    {
        return $this->hasMany(JobboardAssignment::class);
    }


    /**
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client');
    }

    public function assignedClientEmployees()
    {
        return $this->belongsToMany(
            ClientEmployee::class,
            'jobboard_assignments'
        );
    }

    public function recruitmentProcesses()
    {
        return $this->hasMany(RecruitmentProcess::class);
    }
}
