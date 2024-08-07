<?php

namespace App\Observers;

use App\Models\ClientEmployeeContract;
use App\Models\ClientEmployee;

class ClientEmployeeContractObserver
{

    public function created(ClientEmployeeContract $contract)
    {
        $this->updateContractSigningDate($contract->client_employee_id);

        if ($contract->contract_type == 'thuviec') {

            ClientEmployee::where('id', $contract->client_employee_id)->update([
                'probation_start_date' => $contract->contract_signing_date,
                'probation_end_date' => $contract->contract_end_date,
            ]);
        }
    }

    public function deleted(ClientEmployeeContract $contract)
    {
        $this->updateContractSigningDate($contract->client_employee_id);

        if ($contract->contract_type == 'thuviec') {

            ClientEmployee::where('id', $contract->client_employee_id)->update([
                'probation_start_date' => null,
                'probation_end_date' => null,
            ]);
        }
    }

    protected function updateContractSigningDate($client_employee_id)
    {

        $contract = ClientEmployeeContract::select('*')->where('client_employee_id', $client_employee_id)->orderBy('contract_signing_date', 'DESC')->first();

        if (empty($contract)) return;

        $type = $contract->contract_type;

        $contract_type = '';

        switch ($type) {
            case 'co-thoi-han-lan-1':
            case 'co-thoi-han-lan-2':
                $contract_type = 'chinhthuc';
                break;
            case 'khong-xac-dinh-thoi-han':
                $contract_type = 'khongthoihan';
                break;
            case 'thuviec':
                $contract_type = 'thuviec';
                break;
            default:
                $contract_type = 'thoivu';
                break;
        }

        $clientEmployee = ClientEmployee::select('*')->where('id', $client_employee_id)->first();

        if (!empty($clientEmployee)) {

            if ($type != 'thucviec') {
                
                if(!empty($contract->contract_signing_date) && $contract->contract_signing_date == '0000-00-00') {
                    $contract->contract_signing_date = null;
                }
                
                $clientEmployee->update([
                    'type_of_employment_contract' => $contract_type,
                    'official_contract_signing_date' => $contract->contract_signing_date
                ]);
            }
        }
    }

    public function creating(ClientEmployeeContract $contract) {

        if(empty($contract->contract_signing_date)) {
            $contract->contract_signing_date =  null ;
        }

        if(empty($contract->contract_end_date)) {
            $contract->contract_end_date = null;
        }

    }
}
