<?php

namespace App\GraphQL\Mutations;

use App\Models\ClientEmployeeLocationHistory;

class ClientEmployeeLocationHistoryMutator
{
    public function getClientEmployeeLocationHistories($root, array $args){
        $filtered = isset($args['filtered']) ? $args['filtered'] : '';
        if ($filtered) {
            return ClientEmployeeLocationHistory::with('clientEmployee')
                                                ->whereHas('clientEmployee', function ($query) use ($filtered) {
                $query->where('client_employees.full_name', 'LIKE', "%$filtered%")
                    ->orWhere('client_employees.code', 'LIKE', "%$filtered%");
            });
        } else {
            return ClientEmployeeLocationHistory::with('clientEmployee');
        }
    }
}
