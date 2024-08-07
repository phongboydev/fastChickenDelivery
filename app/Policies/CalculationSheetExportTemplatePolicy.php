<?php

namespace App\Policies;

use App\Models\CalculationSheetExportTemplate;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class CalculationSheetExportTemplatePolicy
{
    use HandlesAuthorization;

    private $managerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any calculation sheet client employees.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the calculation sheet client employee.
     *
     * @param User                                 $user
     * @param  \App\CalculationSheetExportTemplate $calculationSheetExportTemplate
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheetExportTemplate $calculationSheetExportTemplate)
    {
        //
    }

    /**
     * Determine whether the user can create calculation sheet client employees.
     *
     * @param User  $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if(!$user->isInternalUser()){
            return $user->client_id == $injected['client_id'] && $user->hasDirectPermission($this->managerPermission);
        }else{

            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_export_template')) {
                return true;
            }else{
                if (!empty($injected['client_id'])) {
                    if ($user->iGlocalEmployee->isAssignedFor($injected['client_id'])) {
                        return true;
                    }
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can update the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheetExportTemplate $calculationSheetExportTemplate)
    {
        if(!$user->isInternalUser()){
            return $user->client_id == $calculationSheetExportTemplate->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_export_template')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetExportTemplate->client_id)) {
                    return true;
                }
                
                return false;
            }
        }
    }

    /**
     * Determine whether the user can delete the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheetExportTemplate $calculationSheetExportTemplate)
    {
        if(!$user->isInternalUser()){
            return $user->client_id == $calculationSheetExportTemplate->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_export_template')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($calculationSheetExportTemplate->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }

    /**
     * Determine whether the user can restore the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetClientEmployee  $calculationSheetClientEmployee
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheetExportTemplate $calculationSheetExportTemplate)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the calculation sheet client employee.
     *
     * @param  User  $user
     * @param  \App\CalculationSheetExportTemplate  $calculationSheetExportTemplate
     *
     * @return mixed
     */
    public function forceDelete(User $user, CalculationSheetExportTemplate $calculationSheetExportTemplate)
    {
        //
    }

    public function upload(User $user, CalculationSheetExportTemplate $model)
    {
        if(!$user->isInternalUser()){
            return $user->client_id == $model->client_id && $user->hasDirectPermission($this->managerPermission);
        }else{
            if($user->getRole() == Constant::ROLE_INTERNAL_DIRECTOR || $user->hasDirectPermission('manage_export_template')) {
                return true;
            }else{
                if ($user->iGlocalEmployee->isAssignedFor($model->client_id)) {
                    return true;
                }
                return false;
            }
        }
    }
}
