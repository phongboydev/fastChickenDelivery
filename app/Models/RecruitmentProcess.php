<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class RecruitmentProcess extends Model
{
  use Concerns\UsesUuid;

  protected $fillable = [
    'code', 'name', 'desc', 'jobboard_job_id', 'leader_id'
  ];

  public function jobboardJob()
  {
    return $this->belongsTo(JobboardJob::class, 'jobboard_job_id', 'id');
  }

  public function assignedClientEmployees()
  {
    return $this->belongsToMany(
      ClientEmployee::class,
      'recruitment_process_assignments'
    );
  }
  
  public function leader() {
    return $this->belongsTo(ClientEmployee::class, 'leader_id', 'id');
  }
}
