<?php

namespace App\Observers;

use Spatie\Period\Period;
use Spatie\Period\Precision;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Boundaries;

use Illuminate\Support\Carbon;
use App\Exceptions\HumanErrorException;

use App\Models\Formula;

class FormulaObserver
{
    public function creating(Formula $formula)
    {
      if( $formula->parent_id ) 
      {
        $this->validateChild($formula);
      }
    }

    public function updating(Formula $formula)
    {
      if((!$formula->parent_id && $formula->getOriginal("begin_effective_at") != $formula->begin_effective_at) || ($formula->getOriginal("end_effective_at") != $formula->end_effective_at)) 
      {
        $this->validateRoot($formula, $formula->id);
      }

      if( $formula->parent_id ) 
      {
        $this->validateChild($formula);
      }
    }

    public function updated(Formula $formula)
    {
        if(!$formula->parent_id) 
        {
          Formula::where('parent_id', $formula->id)->update(['name' => $formula->name, 'func_name' => $formula->func_name]);
        }
    }

    private function validateRoot( $formula )
    {
      $parentId = $formula->id;

      if($formula->begin_effective_at && $formula->end_effective_at) 
      {
        
        $endEffectiveAt = $formula->end_effective_at;

        $allTimeEffective = Formula::where('parent_id', $parentId)
                            ->whereNull('end_effective_at')
                            ->whereDate('begin_effective_at', '<=', $endEffectiveAt)->get();

        if ($allTimeEffective->count() > 0) 
        {
          throw new HumanErrorException(__("error.invalid_time"));
        }
        
        $childFormulas = Formula::where('parent_id', $parentId)->whereNotNull('end_effective_at')->get();

        $currentPeriod = Period::make( Carbon::parse($formula->begin_effective_at), Carbon::parse($formula->end_effective_at), Precision::DAY);

        foreach($childFormulas as $f) 
        {
          $formulaPeriod = Period::make(Carbon::parse($f->begin_effective_at), Carbon::parse($f->end_effective_at), Precision::DAY);
          $overlap = $currentPeriod->overlapSingle($formulaPeriod);

          if ($overlap) 
          {
            throw new HumanErrorException(__("error.invalid_time"));
          }
        }
      }else if($formula->begin_effective_at && !$formula->end_effective_at) {

          $beginEffectiveAt = $formula->begin_effective_at;

          $allTimeEffective = Formula::where('parent_id', $parentId)
                            ->whereNull('end_effective_at')->get();

          if($allTimeEffective->count() > 0) {
            throw new HumanErrorException(__("error.invalid_time"));
          }

          $maxEndEffectiveAt = Formula::where('parent_id', $parentId)
                            ->whereNotNull('end_effective_at')
                            ->whereDate('end_effective_at', '>=', $beginEffectiveAt)->get();

          if($maxEndEffectiveAt->count() > 0) {
            
            throw new HumanErrorException(__("error.invalid_time"));
          }
      }
    }

    private function validateChild( $formula )
    {
      if($formula->begin_effective_at && $formula->end_effective_at) 
      {
        $parentId = $formula->parent_id;
        $endEffectiveAt = $formula->end_effective_at;

        $allTimeEffective = Formula::where('parent_id', $parentId)
                            ->where('id', '!=', $formula->id)
                            ->whereNull('end_effective_at')
                            ->whereDate('begin_effective_at', '<=', $endEffectiveAt)
                            ->orWhere(function($q) use($parentId, $endEffectiveAt) {
                              return $q->where('id', $parentId)
                                      ->whereNull('end_effective_at')
                                      ->whereDate('begin_effective_at', '<=', $endEffectiveAt);
                            })->get();
       
        if ($allTimeEffective->count() > 0) 
        {
          throw new HumanErrorException(__("error.invalid_time"));
        }
        
        $childFormulas = Formula::where('parent_id', $formula->parent_id)
                            ->where('id', '!=', $formula->id)
                            ->whereNotNull('end_effective_at')
                            ->orWhere(function($q) use($parentId, $endEffectiveAt) {
                              return $q->where('id', $parentId)
                                      ->whereNotNull('end_effective_at');
                            })->get();

        $currentPeriod = Period::make( Carbon::parse($formula->begin_effective_at), Carbon::parse($formula->end_effective_at), Precision::DAY);

        foreach($childFormulas as $f) 
        {
          $formulaPeriod = Period::make(Carbon::parse($f->begin_effective_at), Carbon::parse($f->end_effective_at), Precision::DAY);
          $overlap = $currentPeriod->overlapSingle($formulaPeriod);

          if ($overlap) 
          {
            logger('allTimeEffective cc4', [$f]);
            throw new HumanErrorException(__("error.invalid_time"));
          }
        }
      }else if($formula->begin_effective_at && !$formula->end_effective_at) {

          $parentId = $formula->parent_id;
          $beginEffectiveAt = $formula->begin_effective_at;

          $allTimeEffective = Formula::where('parent_id', $parentId)
                            ->where('id', '!=', $formula->id)
                            ->whereNull('end_effective_at')
                            ->orWhere(function($q) use($parentId) {
                              return $q->where('id', $parentId)->whereNull('end_effective_at');
                                        
                            })->get();
          
          if($allTimeEffective->count() > 0) {
            throw new HumanErrorException(__("error.invalid_time"));
          }

          $maxEndEffectiveAt = Formula::where('parent_id', $formula->parent_id)
                            ->where('id', '!=', $formula->id)
                            ->whereNotNull('end_effective_at')
                            ->whereDate('end_effective_at', '>=', $beginEffectiveAt)
                            ->orWhere(function($q) use($parentId, $beginEffectiveAt) {
                              return $q->where('id', $parentId)
                                      ->whereNotNull('end_effective_at')
                                      ->whereDate('end_effective_at', '>=', $beginEffectiveAt);
                            })->get();
         
          if($maxEndEffectiveAt->count() > 0) {
            
            throw new HumanErrorException(__("error.invalid_time"));
          }
      }
    }
}
