<?php

namespace App\Policies;

use App\Exceptions\HumanErrorException;
use App\Models\CalculationSheet;
use App\Models\ClientWorkflowSetting;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\Constant;

class CalculationSheetPolicy
{

    use HandlesAuthorization;

    private $clientManagerPermission = 'manage-payroll';

    /**
     * Determine whether the user can view any calculation sheets.
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
     * Determine whether the user can view the calculation sheet.
     *
     * @param User                  $user
     * @param \App\CalculationSheet $calculationSheet
     *
     * @return mixed
     */
    public function view(User $user, CalculationSheet $calculationSheet)
    {
        //
    }

    /**
     * Determine whether the user can create calculation sheets.
     *
     * @param User $user
     *
     * @return mixed
     */
    public function create(User $user, array $injected)
    {
        if (empty($injected['client_id'])) {
            return false;
        }

        if (!$user->isInternalUser() && $user->client_id != $injected['client_id']) {
            return false;
        }

        $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $injected['client_id'])->first();
        if (!$clientWorkflowSetting->enable_create_payroll) {
            return false;
        }

        $advancedPermissions = ['advanced-manage-payroll', 'advanced-manage-payroll-list-update'];

        return $user->checkHavePermission([$this->clientManagerPermission], $advancedPermissions, $clientWorkflowSetting->advanced_permission_flow, $injected['client_id'], true);
    }

    /**
     * Determine whether the user can update the calculation sheet.
     *
     * @param User                  $user
     * @param \App\CalculationSheet $calculationSheet
     *
     * @return mixed
     */
    public function update(User $user, CalculationSheet $calculationSheet, array $injected = [])
    {
        $newStatus = (isset($injected['status'])) ? $injected['status'] : '';

        if ($newStatus) {
            $isCan = false;
            if (!$user->isInternalUser()) {
                if ($user->client_id != $calculationSheet->client_id) {
                    return false;
                }

                $clientWorkflowSetting = ClientWorkflowSetting::select('*')
                    ->where('client_id', $user->client_id)
                    ->first();
                if (!$clientWorkflowSetting->enable_create_payroll) {
                    return false;
                }

                return $user->hasDirectPermission($this->clientManagerPermission);
            } else {
                $role = $user->getRole();
                switch ($role) {
                    case Constant::ROLE_INTERNAL_STAFF:
                        if (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                            && (($calculationSheet->status == Constant::CREATING_STATUS) || ($calculationSheet->status == Constant::NEW_STATUS) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_CLIENT_REJECTED) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_LEADER_DECLINED) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED))
                            && (($newStatus == Constant::PROCESSED_STATUS) || ($newStatus == Constant::NEW_STATUS))
                        ) {
                            $isCan = true;
                        }
                        break;
                    case Constant::ROLE_INTERNAL_LEADER:
                        if ((($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                                && ($calculationSheet->status == Constant::PROCESSED_STATUS)
                                && (($newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW) || ($newStatus == Constant::CALC_SHEET_STATUS_LEADER_DECLINED))
                            ) ||
                            (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                                && ($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED)
                                && ($newStatus == Constant::CALC_SHEET_STATUS_CLIENT_REVIEW)
                            ) ||
                            (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                                && ($calculationSheet->status != Constant::CALC_SHEET_STATUS_PAID)
                                && ($newStatus == Constant::NEW_STATUS)
                            ) ||
                            (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                                && (($calculationSheet->status == Constant::CREATING_STATUS) || ($calculationSheet->status == Constant::NEW_STATUS) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_CLIENT_REJECTED) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_LEADER_DECLINED) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED))
                                && ($newStatus == Constant::NEW_STATUS || $newStatus == Constant::PROCESSED_STATUS || $newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW)
                            ) ||
                            (($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW)
                                && (($newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED) || ($newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED) || ($newStatus == Constant::CALC_SHEET_STATUS_CLIENT_REVIEW))
                            )
                        ) {
                            $isCan = true;
                        }
                        break;
                    case Constant::ROLE_INTERNAL_ACCOUNTANT:
                        if ((($calculationSheet->status == Constant::CALC_SHEET_STATUS_CLIENT_APPROVED)
                                && ($newStatus == Constant::CALC_SHEET_STATUS_PAID)
                            ) ||
                            (($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED)
                                && ($newStatus == Constant::CALC_SHEET_STATUS_PAID)
                            )
                        ) {
                            $isCan = true;
                        }
                        break;
                    case Constant::ROLE_INTERNAL_DIRECTOR:
                        if (($calculationSheet->status == Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW)
                            && (($newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED) || ($newStatus == Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED) || ($newStatus == Constant::CALC_SHEET_STATUS_CLIENT_REVIEW))
                        ) {
                            $isCan = true;
                        }
                        break;

                    default:
                        $isCan = false;
                        break;
                }
            }
            return $isCan;
        } else {
            if (!$user->isInternalUser()) {
                if ($user->client_id != $calculationSheet->client_id) {
                    return false;
                }

                $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();

                $advancedPermissions = ['advanced-manage-payroll', 'advanced-manage-payroll-list-update'];

                return $user->checkHavePermission([$this->clientManagerPermission], $advancedPermissions, $clientWorkflowSetting->advanced_permission_flow, $calculationSheet->client_id);
            } else {
                $role = $user->getRole();
                switch ($role) {
                    case Constant::ROLE_INTERNAL_STAFF:
                    case Constant::ROLE_INTERNAL_LEADER:
                        if (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id)) && (($calculationSheet->status == Constant::NEW_STATUS) || ($calculationSheet->status == Constant::CALC_SHEET_STATUS_LEADER_DECLINED))) {
                            return true;
                        }
                        return false;
                    case Constant::ROLE_INTERNAL_ACCOUNTANT:
                    case Constant::ROLE_INTERNAL_DIRECTOR:
                        return true;
                    default:
                        return false;
                }
            }
        }
    }

    /**
     * Determine whether the user can delete the calculation sheet.
     *
     * @param User                  $user
     * @param \App\CalculationSheet $calculationSheet
     *
     * @return mixed
     */
    public function delete(User $user, CalculationSheet $calculationSheet)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $calculationSheet->client_id) {
                return false;
            }

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')
                ->where('client_id', $user->client_id)
                ->first();
            if (!$clientWorkflowSetting->enable_create_payroll) {
                return false;
            }

            $advancedPermission = ['advanced-manage-payroll', 'advanced-manage-payroll-list-delete'];
            $validClientId = $user->checkHavePermission([$this->clientManagerPermission], $advancedPermission, $clientWorkflowSetting->advanced_permission_flow);

            $validCalculationSheet = in_array($calculationSheet->status, [
                'new',
                'creating',
                'client_review',
            ]) && ($user->client_id == $calculationSheet->client_id);

            if ($validClientId && $validCalculationSheet) {
                return true;
            }

            return false;
        } else {
            if (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id)) && in_array($calculationSheet->status, [
                Constant::NEW_STATUS,
                Constant::CALC_SHEET_STATUS_CLIENT_REJECTED,
                Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED,
                Constant::CALC_SHEET_STATUS_LEADER_DECLINED,
            ])) {
                return true;
            }
            return false;
        }
    }

    /**
     * Determine whether the user can restore the calculation sheet.
     *
     * @param User                  $user
     * @param \App\CalculationSheet $calculationSheet
     *
     * @return mixed
     */
    public function restore(User $user, CalculationSheet $calculationSheet)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the calculation sheet.
     *
     * @param User $user
     * @param \App\CalculationSheet $calculationSheet
     *
     * @return mixed
     * @throws HumanErrorException
     */
    public function forceDelete(User $user, CalculationSheet $calculationSheet)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $calculationSheet->client_id) {
                throw new HumanErrorException(__('no_permissions'));
            }

            $clientWorkflowSetting = ClientWorkflowSetting::select('*')->where('client_id', $user->client_id)->first();
            if (!$clientWorkflowSetting->enable_create_payroll) {
                throw new HumanErrorException(__('no_permissions'));
            }

            // Check advanced permission flow
            $advancedPermission = ['advanced-manage-payroll', 'advanced-manage-payroll-list-delete'];
            $validClientId = $user->checkHavePermission([$this->clientManagerPermission], $advancedPermission, $clientWorkflowSetting->advanced_permission_flow);
            $validCalculationSheet = in_array($calculationSheet->status, [
                'new',
                'creating',
                'client_review',
                'error',
            ]);

            if ($validClientId && $validCalculationSheet) {
                return true;
            }

            throw new HumanErrorException(__('no_permissions'));
        } else {
            if (($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id)) && in_array($calculationSheet->status, [
                Constant::CREATING_STATUS,
                Constant::NEW_STATUS,
                Constant::CALC_SHEET_STATUS_CLIENT_REJECTED,
                Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED,
                Constant::CALC_SHEET_STATUS_LEADER_DECLINED,
                Constant::ERROR_STATUS,
            ])) {
                return true;
            }
            throw new HumanErrorException(__('no_permissions'));
        }
    }

    public function upload(User $user, CalculationSheet $calculationSheet)
    {
        return false;
    }

    public function updatePayslipDate(User $user, CalculationSheet $calculationSheet)
    {
        if (!$user->isInternalUser()) {
            if ($user->client_id != $calculationSheet->client_id) {
                return false;
            }

            $clientWorkflowSetting = ClientWorkflowSetting::select(['advanced_permission_flow'])->where('client_id', $user->client_id)->first();

            $advancedPermissions = ['advanced-manage-payroll', 'advanced-manage-payroll-list-update'];

            return $user->checkHavePermission([$this->clientManagerPermission], $advancedPermissions, $clientWorkflowSetting->advanced_permission_flow, $calculationSheet->client_id);

        } else {
            $role = $user->getRole();
            switch ($role) {
                case Constant::ROLE_INTERNAL_STAFF:
                    if (
                        ($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                        && ( $calculationSheet->status == Constant::NEW_STATUS
                            || $calculationSheet->status ==  Constant::PROCESSED_STATUS
                            || $calculationSheet->status == Constant::CALC_SHEET_STATUS_LEADER_DECLINED
                        )
                    ) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_INTERNAL_LEADER:
                    if (
                        ($user->iGlocalEmployee->isAssignedFor($calculationSheet->client_id))
                        && ($calculationSheet->status == Constant::NEW_STATUS
                            || $calculationSheet->status ==  Constant::PROCESSED_STATUS
                            || $calculationSheet->status == Constant::CALC_SHEET_STATUS_LEADER_DECLINED
                            || $calculationSheet->status ==  Constant::CALC_SHEET_STATUS_DIRECTOR_REVIEW
                            || $calculationSheet->status ==  Constant::CALC_SHEET_STATUS_DIRECTOR_APPROCED
                            || $calculationSheet->status ==  Constant::CALC_SHEET_STATUS_DIRECTOR_DECLINED
                        )
                    ) {
                        return true;
                    }
                    return false;
                case Constant::ROLE_INTERNAL_ACCOUNTANT:
                case Constant::ROLE_INTERNAL_DIRECTOR:
                    return true;
                default:
                    return false;
            }
        }
    }
}
