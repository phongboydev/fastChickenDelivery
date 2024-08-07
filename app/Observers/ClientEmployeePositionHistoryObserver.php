<?php

namespace App\Observers;

use App\Models\ClientEmployee;
use App\Models\ClientEmployeePositionHistory;
use Carbon\Carbon;

class ClientEmployeePositionHistoryObserver
{

    public function updating(ClientEmployee $clientEmployee)
    {

        $isLikeDepartmentBefore   = ClientEmployee::where('id', $clientEmployee->id)
                                                    ->where('department', $clientEmployee->department)->first();

        $isLikePositionBefore     = ClientEmployee::where('id', $clientEmployee->id)
                                                    ->where('position', $clientEmployee->position)->first();

        if( empty($isLikeDepartmentBefore) || empty($isLikePositionBefore) ) {

            $oldClientEmployee      = ClientEmployee::where('id', $clientEmployee->id)->first();
            if (!empty($currentClientEmployee)) {
                ClientEmployeePositionHistory::create([
                    'client_employee_id' => $clientEmployee->id,
                    'old_department' => $oldClientEmployee->department,
                    'new_department' => $clientEmployee->department,
                    'old_position' => $oldClientEmployee->position,
                    'new_position' => $clientEmployee->position,
                    'created_at' => Carbon::now(),
                ]);
            }

        }
    }
}
