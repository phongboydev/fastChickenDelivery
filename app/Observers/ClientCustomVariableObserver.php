<?php

namespace App\Observers;

use App\Models\ClientCustomVariable;
use App\Exceptions\HumanErrorException;

class ClientCustomVariableObserver
{
    /**
     * Handle the assignment project "created" event.
     *
     * @param  \App\AssignmentProject  $assignmentProject
     * @return void
     */
    public function creating(ClientCustomVariable $clientCustomVariable)
    {
      $hasCustomVariable = ClientCustomVariable::where('client_id', $clientCustomVariable->client_id)
                                                ->where('variable_name', $clientCustomVariable->variable_name)->first();
      
      if($hasCustomVariable) {
        throw new HumanErrorException(__('trung_ten_bien'));
      }
    }

    public function updating(ClientCustomVariable $clientCustomVariable)
    {
      $beforeCustomVariable = ClientCustomVariable::where('client_id', $clientCustomVariable->client_id)
                                                ->where('variable_name', $clientCustomVariable->variable_name)->first();
      
      if($beforeCustomVariable && ($beforeCustomVariable->id != $clientCustomVariable->id)) {
        throw new HumanErrorException(__('trung_ten_bien'));
      }
    }
}
