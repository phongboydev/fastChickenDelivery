<?php

namespace App\Policies;

use App\Models\PaymentRequestExportTemplate;
use App\Support\Constant;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentRequestExportTemplatePolicy
{
    use HandlesAuthorization;

    private $clientManagerPermission = ['manage-payment-request'];

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user, array $injected)
    {
         return $this->isPermission($user, $injected['client_id']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\PaymentRequestExportTemplate  $paymentRequestExportTemplate
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, PaymentRequestExportTemplate $paymentRequestExportTemplate)
    {
        return $this->isPermission($user, $paymentRequestExportTemplate->client_id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\User  $user
     * @param  \App\Models\PaymentRequestExportTemplate  $paymentRequestExportTemplate
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, PaymentRequestExportTemplate $paymentRequestExportTemplate)
    {
        return $this->isPermission($user, $paymentRequestExportTemplate->client_id);
    }

    public function upload(User $user, PaymentRequestExportTemplate $paymentRequestExportTemplate)
    {
        return true;
    }

    public function isPermission($user, $clientId)
    {
        $isHavePermission = false;
        if (!$user->isInternalUser()) {
            if ($user->client_id == $clientId && $user->checkHavePermission($this->clientManagerPermission, $this->clientManagerPermission)) {
                $isHavePermission = true;
            }
        } else {
            $isHavePermission = true;
        }
        return $isHavePermission;
    }
}
