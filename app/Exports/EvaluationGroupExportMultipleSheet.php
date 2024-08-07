<?php

namespace App\Exports;

use App\Models\EvaluationObject;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class EvaluationGroupExportMultipleSheet implements WithMultipleSheets
{

    protected $evaluationGroupId;

    public function __construct($evaluationGroupId)
    {
        $this->evaluationGroupId = $evaluationGroupId;
    }

    public function sheets(): array
    {
        $evaluationObjects = EvaluationObject::where('evaluation_group_id', $this->evaluationGroupId)
                                 ->get();
        $sheets = [];
        $sheets[] = new EvaluationGroupExport($this->evaluationGroupId);
        $sheets[]= new EvaluationStepExport($this->evaluationGroupId);
        $sheets[] = new EvaluationQuestionTemplateExport($this->evaluationGroupId);
        foreach($evaluationObjects as $evaluationObject){
            $sheets[] = new EvaluationObjectExport($evaluationObject->id);
        }
        return $sheets;
    }
}
