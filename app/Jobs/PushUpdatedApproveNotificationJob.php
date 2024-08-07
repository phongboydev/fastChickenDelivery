<?php

namespace App\Jobs;

use App\Models\Approve;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Str;

class PushUpdatedApproveNotificationJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Approve $approve;
    protected string $state;

    public function __construct(Approve $approve, string $state)
    {
        $this->approve = $approve;
        $this->state = $state;
    }

    public function handle(): void
    {
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
            'CLIENT_REQUEST_TIMESHEET'
        ];

        if (!in_array($this->approve->type, $mobileSupportedTypes)) {
            return;
        }

        $user = User::query()->where("id", $this->approve->original_creator_id)->first();
        if (!$user) {
            return;
        }

        // load this user locale to send notification in correct language
        $user->loadUserLocale();

        $state = '';
        switch ($this->state) {
            case 'approved':
                $state = __('approve_state.approved');
                break;
            case 'rejected':
                $state = __('approve_state.canceled');
                break;
            case 'switch':
                $state = __('approve.switch');
                break;
        }
        $title = __('device_notification.updated_approve.title', [
            'type' => __('approve_names.' . strtolower($this->approve->type))
        ]);
        $body = __('device_notification.updated_approve.content', [
            'type' => __('approve_names.' . strtolower($this->approve->type)),
            'state' => Str::lower($state),
        ]);
        $user->pushDeviceNotification(
            $title,
            $body,
            [
                'type' => 'updated_approve',
                'approve_id' => $this->approve->id,
                'approve_type' => $this->approve->type,
                'approve_creator_id' => $this->approve->creator_id,
                'approve_assignee_id' => $this->approve->assignee_id,
                'approve_created_at' => $this->approve->created_at,
            ]
        );
    }
}
