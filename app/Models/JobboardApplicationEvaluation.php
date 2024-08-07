<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\UsesUuid;

class JobboardApplicationEvaluation extends Model
{
  use Concerns\UsesUuid;

  protected $fillable = [
    'jobboard_application_id',
    'recruitment_process_id',
    'status',
    'overview',
    'updated_by'
  ];
  //const STATUS = [0=>'Fail', 1=>'Pass',2=>'Pending']; // 0: Fail, 1: Pass, 2: Pending

  public function jobboardApplication()
  {
    return $this->belongsTo(JobboardApplication::class);
  }

  public function recruitmentProcess()
  {
    return $this->belongsTo(RecruitmentProcess::class);
  }

  public function lastUpdatedBy() {
    return $this->belongsTo(ClientEmployee::class, 'updated_by', 'id');
  }
  protected static function boot()
  {
    parent::boot();
    static::created(function ($model) {
        if($model->status == 0 )
        {
            $model->jobboardApplication->update([
                'status' => 4,
            ]);
        }
    });
  }

}
