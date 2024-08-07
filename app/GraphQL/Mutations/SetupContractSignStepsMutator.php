<?php

namespace App\GraphQL\Mutations;

use App\Models\Contract;
use App\Models\ContractSignStep;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CustomException;

class SetupContractSignStepsMutator
{

    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        if (empty($args['steps'])) {
            throw new CustomException(
                __("warning.WR0001.e_contract"),
                'ValidationException',
                'WR0001',
                [],
                "warning",
                "e_contract"
            );
        } else {
            $contract = Contract::query()
                ->authUserAccessible()
                ->where('id', $args['id'])
                ->firstOrFail();

            if ($contract->is_sign_setup) {
                throw new CustomException(
                    __("warning.WR0002.e_contract"),
                    'ValidationException',
                    'WR0003',
                    [],
                    "warning",
                    "e_contract"
                );
            }

            if ($contract->contract_type == 'hop_dong_nhan_vien' && $contract->staff_confirm === NULL) {
                throw new CustomException(
                    __("warning.WR0003.e_contract"),
                    'ValidationException',
                    'WR0003',
                    [],
                    "warning",
                    "e_contract"
                );
            }

            DB::transaction(function () use ($args, $contract) {
                // clean old steps if any
                ContractSignStep::query()
                    ->where('contract_id', $contract->id)
                    ->delete();

                foreach ($args['steps'] as $step) {
                    $stepModel = new ContractSignStep($step);
                    $stepModel->client_id = $contract->client_id;
                    $stepModel->contract_id = $contract->id;
                    $stepModel->save();
                }

                $contract->ma_tham_chieu = null;
                $contract->company_signed_at = null;
                $contract->staff_signed_at = null;
                $contract->is_sign_setup = true;
                $contract->save();
                // TODO future function: send notification
            });

            return $contract;
        }
    }
}
