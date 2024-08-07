<?php

namespace App\Support;

use App\Models\EvaluationStep;
use App\Models\EvaluationParticipant;
use App\Exceptions\CustomException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

trait EvaluationParticipantTrait
{

    public function validateScoreboard($scoreboard){
        $scoreboardCollection = collect(json_decode($scoreboard, true));
        foreach($scoreboardCollection as $answer){
           if(empty($answer['question_id']) || empty($answer['answer']) || empty($answer['score'])){
                throw new CustomException(__("model.question_library.content_has_not_been_entered"), CustomException::class);
           }
        }
    }
    
    public function lockEvaluationParticipant($evaluationObjectId)
    {
        $key1 = $evaluationObjectId;
        $key2 = $evaluationObjectId.'-'.Auth::user()->id;
        if(RateLimiter::attempts($key1) == 0) {
            RateLimiter::hit( $key1, 1800);
            RateLimiter::hit( $key2, 1800);
        }
        else{
            if(RateLimiter::attempts($key2) == 0){
                throw new CustomException(__("warning.another_person.evaluating"), CustomException::class);
            }
        }
    }


    public function unlockEvaluationParticipant($evaluationObjectId){
        $key1 = $evaluationObjectId;
        $key2 = $evaluationObjectId.'-'.Auth::user()->id;
        RateLimiter::clear($key1);
        RateLimiter::clear($key2);
    }

}