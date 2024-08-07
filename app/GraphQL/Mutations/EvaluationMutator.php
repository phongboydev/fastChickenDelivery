<?php

namespace App\GraphQL\Mutations;

use App\Models\Evaluation;
use App\Models\EvaluationGroup;
use App\Models\EvaluationUser;
use Exception;

class EvaluationMutator
{

    public function deleteEvaluation($root, array $args) {
				$client_id = $args['client_id'];
				$evaluation_group_id = $args['evaluation_group_id'];

        try {
            EvaluationUser::where('client_id', $client_id)
              ->where('evaluation_group_id', $evaluation_group_id)
              ->delete();

            Evaluation::where('client_id', $client_id)
              ->where('evaluation_group_id', $evaluation_group_id)
              ->delete();

            EvaluationGroup::where('client_id', $client_id)
              ->where('id', $evaluation_group_id)
              ->delete();

            return "ok";
        }
        catch(Exception $e) {
            logger()->error("deleteEvaluation error: " . $e->getMessage());
            return "fail";
        }

    }
}