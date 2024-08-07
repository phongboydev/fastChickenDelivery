<?php

namespace App\Observers;

use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;
use App\Models\ClientEmployeeGroupAssignment;

use App\Exceptions\HumanErrorException;

class ClientEmployeeGroupAssignmentObserver
{

  public function creating(ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
  {

    $this->_validate( $clientEmployeeGroupAssignment );
  }

  public function updating(ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
  {
    $this->_validate( $clientEmployeeGroupAssignment );
  }

  public function deleting(ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
  {
    $this->_validate( $clientEmployeeGroupAssignment );
  }

  public function deleted(ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
  {
    $approveFlows = ApproveFlow::select('id', 'flow_name')->where('group_id', $clientEmployeeGroupAssignment->client_employee_group_id )->get();

    if($approveFlows->isNotEmpty())
    {
      $approveFlowIds = $approveFlows->pluck('id');

      ApproveFlowUser::whereIn('approve_flow_id', $approveFlowIds)
                      ->where('user_id', $clientEmployeeGroupAssignment->clientEmployee['user_id'])->delete();
    }
  }

  private function _validate(ClientEmployeeGroupAssignment $clientEmployeeGroupAssignment)
  {
    if($clientEmployeeGroupAssignment->clientEmployee)
    {
      $clientEmployee = $clientEmployeeGroupAssignment->clientEmployee;
      $userId = isset($clientEmployee['user_id']) && $clientEmployee['user_id'] ? $clientEmployee['user_id'] : false;

      if(!$userId) return true;

      $approves = Approve::whereNull('approved_at')
                          ->whereNull('declined_at')
                          ->where(function($query) use($userId) {
                            $query->where('assignee_id', $userId)->orWhere('original_creator_id', $userId);
                          })->get();

      if ( $approves->isNotEmpty() ) {

        throw new HumanErrorException(__("error_message_client_employee_group_assignment", ['code' => $clientEmployee->code, 'name' => $clientEmployee->full_name]));
      }
    }
  }
}
