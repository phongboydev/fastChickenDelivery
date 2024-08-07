<?php

namespace App\GraphQL\Queries;

use App\Models\EvaluationParticipant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetAssignedEvaluationParticipantQuery
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
        return EvaluationParticipant::with('evaluationStep')
            ->whereHas('evaluationStep', function ($query) {
                $query->where('isSelf', false)
                    ->whereHas('evaluationGroup', function ($query) {
                        $query->where('deadline_begin', '<=', Carbon::now()->toDateString());
                    });
            });
    }
}
