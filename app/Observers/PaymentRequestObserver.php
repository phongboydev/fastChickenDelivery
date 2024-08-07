<?php

namespace App\Observers;

use App\Models\PaymentRequest;
use App\Models\PaymentRequestStateHistory;
use App\Notifications\PaymentRequestNotification;
use App\Support\WorktimeRegisterHelper;
use App\User;
use Illuminate\Support\Facades\Auth;

class PaymentRequestObserver
{

    public function creating(PaymentRequest $paymentRequest)
    {
        $lastPaymentRequestByCategory = PaymentRequest::where([
            ['client_employee_id', $paymentRequest->client_employee_id],
            ['category', $paymentRequest->category]
        ])->with('clientEmployee')->latest()->first();

        // Check exiting payment request by category
        if ($lastPaymentRequestByCategory) {
            $paymentRequest->code = WorktimeRegisterHelper::generateNextID($lastPaymentRequestByCategory->code);
        } else {
            $paymentRequest->code = strtoupper($paymentRequest->clientEmployee->code . '_' . implode('_', explode(' ', $paymentRequest->category)) . '-00000');
        }
    }
    /**
     * Handle the PaymentRequest "created" event.
     *
     * @param  \App\Models\PaymentRequest  $paymentRequest
     * @return void
     */
    public function created(PaymentRequest $paymentRequest)
    {
        //
    }

    /**
     * Handle the PaymentRequest "updated" event.
     *
     * @param  \App\Models\PaymentRequest  $paymentRequest
     * @return void
     */
    public function updated(PaymentRequest $paymentRequest)
    {
        $client_employee = auth()->user()->clientEmployee;
        if($paymentRequest->getOriginal('state') !== $paymentRequest['state']){
            PaymentRequestStateHistory::create(array(
                'state' =>  $paymentRequest['state'],
                'client_employee_id' =>  $client_employee['id'],
                'payment_request_id' => $paymentRequest['id'],
            ));
        }
    }

    /**
     * Handle the PaymentRequest "deleted" event.
     *
     * @param  \App\Models\PaymentRequest  $paymentRequest
     * @return void
     */
    public function deleting(PaymentRequest $paymentRequest)
    {
        $deletedByUser = Auth::user();

        $user_ids = $paymentRequest->approves->pluck('assignee_id');

        $users = User::whereIn('id', $user_ids)->get();

        foreach ($users as $user) {
            if ($deletedByUser->id != $user->id) {
                $user->notify(new PaymentRequestNotification($paymentRequest->title, $deletedByUser->name));
            }
        }

        $deletedByUser->notify(new PaymentRequestNotification($paymentRequest->title, ''));
    }

    /**
     * Handle the PaymentRequest "restored" event.
     *
     * @param  \App\Models\PaymentRequest  $paymentRequest
     * @return void
     */
    public function restored(PaymentRequest $paymentRequest)
    {
        //
    }

    /**
     * Handle the PaymentRequest "force deleted" event.
     *
     * @param  \App\Models\PaymentRequest  $paymentRequest
     * @return void
     */
    public function forceDeleted(PaymentRequest $paymentRequest)
    {
        //
    }
}
