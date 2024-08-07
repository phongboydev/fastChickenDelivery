<?php

namespace App\GraphQL\Mutations;

use App\Models\Contract;
use App\Models\ContractSignStep;
use Carbon\Carbon;

class UploadSignContractStepMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $id = $args['id'];
        $step = $args['step'];
        $base64 = $args['base64'];

        /** @var Contract $contract */
        $contract = Contract::query()->authUserAccessible()->findOrFail($id);
        /** @var ContractSignStep $contractSignStep */
        $contractSignStep = $contract->contractSignSteps()->where('step', $step)->firstOrFail();

        try {
            $contractSignStep->addMediaFromBase64($base64, ["application/pdf"])
                             ->toMediaCollection($contractSignStep->getPdfCollectionName(), "minio");
            $contractSignStep->signed_at = Carbon::now();
            if ($step == "company") {
                $contract->company_signed_at = Carbon::now();
            }
            elseif ($step == "employee") {
                $contract->staff_signed_at = Carbon::now();
            }
            $contractSignStep->save();
            $pendingStep = $contract->contractSignSteps()->whereNull('signed_at')->first();
            if ($pendingStep) {
                if ($pendingStep->step == "company") {
                    $contract->status = Contract::STATUS_WAIT_FOR_COMPANY;
                }
                elseif ($pendingStep->step == "employee") {
                    $contract->status = Contract::STATUS_WAIT_FOR_EMPLOYEE;
                }
            } else {
                $contract->status = Contract::STATUS_DONE;
            }
            $contract->save();
            return true;
        } catch (\Exception $e) {
            logger($e->getMessage());
            return false;
        }
    }
}
