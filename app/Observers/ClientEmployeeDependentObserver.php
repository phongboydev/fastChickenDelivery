<?php

namespace App\Observers;

use App\Models\ClientEmployeeDependent;
use App\Models\ClientEmployee;

class ClientEmployeeDependentObserver
{

    public function created(ClientEmployeeDependent $clientEmployeeDependent)
    {
        $this->updateEmployee($clientEmployeeDependent->client_employee_id);
    }

    public function deleted(ClientEmployeeDependent $clientEmployeeDependent)
    {
        $this->updateEmployee($clientEmployeeDependent->client_employee_id);
    }

    protected function updateEmployee($client_employee_id)
    {

        $dependent = ClientEmployeeDependent::select('*')->where('client_employee_id', $client_employee_id)->count();
        $clientEmployee = ClientEmployee::select('*')->where('id', $client_employee_id)->exists();
        if($clientEmployee){
            ClientEmployee::where('id', $client_employee_id)->update([
                'number_of_dependents' => $dependent
            ]);
        }
    }
}
