<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Models\ClientEmployeeGroupAssignment;
use App\Models\ClientEmployee;
use Illuminate\Support\Facades\DB;

class ClientEmployeeGroupMutator
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        // TODO implement the resolver
    }

    public function toggleGroupApprovalForEmployee($root, array $args)
    {
        $user = auth()->user();

        if (!$user->isInternalUser() && $user->client_id !== $args['client_id']) {
            return false;
        }

        try {
            DB::beginTransaction();

            foreach ($args['input'] as $input) {
                foreach ($input['expand'] as $item) {
                    if ($item['approval'] === false) {
                        $groupCount = ClientEmployeeGroupAssignment::where([
                            'client_employee_id' => $item['client_employee_id'],
                            'approval' => true
                        ])
                            ->count();

                        if ($groupCount > 1) {
                            ClientEmployeeGroupAssignment::where([
                                'client_employee_group_id' => $input['client_employee_group_id'],
                                'client_employee_id' => $item['client_employee_id'],
                            ])->update(['approval' => $item['approval']]);
                        }
                    } else {
                        ClientEmployeeGroupAssignment::where([
                            'client_employee_group_id' => $input['client_employee_group_id'],
                            'client_employee_id' => $item['client_employee_id'],
                        ])->update(['approval' => $item['approval']]);
                    }
                }
            }

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new CustomException(
                __('model.employees.update_failed'),
                'CustomException'
            );
        }
    }

    public function getClientEmployeeGroupApproval($root, array $args)
    {
        return ClientEmployee::authUserAccessible()
            ->status()
            ->where([
                'client_id' => $args['client_id'],
            ])
            ->whereHas('clientEmployeeGroupAssignment', function ($query) {
                $query->has('clientEmployeeGroup');
            })
            ->get();
    }
}
