<?php

namespace App\Policies;

use App\Models\ClientWorkflowSetting;
use App\Models\Supplier;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class SupplierPolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-payment-request';

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Supplier $supplier, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id == $supplier->client_id && $user->hasDirectPermission($this->managerPermission) || $user->getRole() == Constant::ROLE_CLIENT_MANAGER;
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                return $user->iGlocalEmployee->isAssignedFor($injected['client_id']);
            }
        }
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id === $injected['client_id'] && $user->hasDirectPermission($this->managerPermission) ||
                $user->client_id === $injected['client_id'] && $user->getRole() == Constant::ROLE_CLIENT_MANAGER ||
                $user->client_id === $injected['client_id'] && $this->getSettingInternal($injected['client_id']);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                return $user->iGlocalEmployee->isAssignedFor($injected['client_id']);
            }
        }
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Supplier $supplier, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id === $injected['client_id'] && $user->hasDirectPermission($this->managerPermission) ||
                $user->client_id === $injected['client_id'] && $user->getRole() == Constant::ROLE_CLIENT_MANAGER ||
                $user->client_id === $injected['client_id'] && $this->getSettingInternal($injected['client_id']) && $this->checkUpdateBySelfClientEmployee($user, $supplier, $injected);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                return $user->iGlocalEmployee->isAssignedFor($supplier->client_id);
            }
        }
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Supplier $supplier, array $injected)
    {
        if (!$user->isInternalUser()) {
            return $user->client_id === $supplier->client_id && $user->hasDirectPermission($this->managerPermission) ||
                $user->client_id === $supplier->client_id && $user->getRole() == Constant::ROLE_CLIENT_MANAGER ||
                $user->client_id === $supplier->client_id && $this->getSettingInternal($supplier->client_id) && $this->checkDeleteMultipleBySelfClientEmployee($user, $injected);
        } else {
            if ($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_clients')) {
                return true;
            } else {
                return $user->iGlocalEmployee->isAssignedFor($supplier->client_id);
            }
        }
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Supplier $supplier)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Supplier $supplier)
    {

    }

    public function getSettingInternal($clientId) {
        $setting = false;
        $clientSetting = ClientWorkflowSetting::where('client_id',$clientId)->first();
        if($clientSetting && $clientSetting->enable_create_supplier_for_individual) {
            $setting = true;
        }
        return $setting;
    }

    public function checkUpdateBySelfClientEmployee($user,$supplier, $injected) {
        $isUpdate = false;
        if($injected['id'] == $supplier['id']) {
            $clientEmployeeID = $user->clientEmployee->id;
            if($clientEmployeeID == $supplier['client_employee_id']) {
                $isUpdate = true;
            }
        }
        return $isUpdate;
    }

    public function checkDeleteMultipleBySelfClientEmployee($user, $injected) {

        $isDelete = false;
        $clientEmployeeID = $user->clientEmployee->id;
        $listIdSupplierByClientEmployeeID = Supplier::where('client_employee_id', $clientEmployeeID)->get();

        if(isset($injected['ids'])) {
            foreach ($injected['ids'] as $id) {
                $isDelete = false;
                foreach ($listIdSupplierByClientEmployeeID as $item) {
                    if($id == $item->id) {
                        $isDelete = true;
                        break;
                    }
                }
            }
        }
        return $isDelete;
    }
}
