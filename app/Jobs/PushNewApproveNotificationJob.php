<?php

namespace App\Jobs;

use App\Models\Approve;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushNewApproveNotificationJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Approve $approve;

    public function __construct(Approve $approve)
    {
        $this->approve = $approve;
    }

    public function handle(): void
    {
        $user = User::query()->where("id", $this->approve->assignee_id)->first();
        if (!$user) {
            return;
        }

        $user->loadUserLocale();
        $mobileSupportedTypes = [
            'CLIENT_REQUEST_OFF',
            'CLIENT_REQUEST_CONG_TAC',
            'CLIENT_REQUEST_OT',
            'CLIENT_REQUEST_OT_ASSIGNMENT',
            'CLIENT_REQUEST_CANCEL_OT',
            'CLIENT_REQUEST_CANCEL_OFF',
            'CLIENT_REQUEST_CANCEL_CONG_TAC',
            'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR',
            'CLIENT_REQUEST_EDITING_FLEXIBLE_TIMESHEET',
            'CLIENT_REQUEST_CHANGED_SHIFT',
            'CLIENT_REQUEST_ROAD_TRANSPORTATION',
            'CLIENT_REQUEST_AIRLINE_TRANSPORTATION',
            'CLIENT_REQUEST_CANCEL_ROAD_TRANSPORTATION',
            'CLIENT_REQUEST_CANCEL_AIRLINE_TRANSPORTATION',
            'CLIENT_REQUEST_PAYMENT',
            'CLIENT_REQUEST_TIMESHEET',
        ];

        if (!in_array($this->approve->type, $mobileSupportedTypes)) {
            return;
        }

        $title = __('device_notification.new_approve.title', [
            'type' => __('approve_names.' . strtolower($this->approve->type))
        ]);
        $body = __('device_notification.new_approve.content', [
            'creator' => $this->approve->originalCreator->name,
            'type' => __('approve_names.' . strtolower($this->approve->type))
        ]);
        $user->pushDeviceNotification(
            $title,
            $body,
            [
                'type' => 'new_approve',
                'approve_id' => $this->approve->id,
                'approve_type' => $this->approve->type,
                'approve_creator_id' => $this->approve->creator_id,
                'approve_assignee_id' => $this->approve->assignee_id,
                'approve_created_at' => $this->approve->created_at,
            ]
        );
    }
}
