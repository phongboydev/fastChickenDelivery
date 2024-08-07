<?php

namespace App\Observers;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ClientEmployeeGroup;

class ClientEmployeeGroupObserver
{

    public function deleting(ClientEmployeeGroup $clientEmployeeGroup)
    {
      logger('@ClientEmployeeGroupObserver::deleting - ' . $clientEmployeeGroup->id);

      $approveFlows = ApproveFlow::where('client_id', $clientEmployeeGroup->client_id)
                                ->where('group_id', $clientEmployeeGroup->id)->get();

      logger('@ClientEmployeeGroupObserver::deleting - approveFlows: ', [$approveFlows]);

      if($approveFlows->isNotEmpty()) {
        foreach($approveFlows as $approveFlow) {
          $approveFlow->delete();
        }
      }
    }
}