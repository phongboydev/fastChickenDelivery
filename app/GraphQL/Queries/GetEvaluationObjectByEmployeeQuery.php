<?php

namespace App\GraphQL\Queries;

use App\Models\EvaluationObject;
use Carbon\Carbon;

class GetEvaluationObjectByEmployeeQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        return $this->handle();
    }

    public function handle()
    {
        return EvaluationObject::with('evaluationGroup')
            ->whereHas('evaluationGroup', function ($query) {
                $query->where('deadline_begin', '<=', Carbon::now()->toDateString());
            });
    }
}
