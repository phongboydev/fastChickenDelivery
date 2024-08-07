<?php

namespace App\Notifications;

use App\User;
use App\Models\Client;
use App\Models\CalculationSheet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Support\Constant;
use App\Support\MailEngineTrait;
use App\Support\TranslationTrait;

class ApproveNotification extends Notification implements ShouldQueue
{
    use Queueable, MailEngineTrait, TranslationTrait;

    protected $approve;
    protected $action;
    protected $status;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($approve, $action, $status = Constant::PROCESSING_STATUS)
    {
        $this->approve = $approve;
        $this->action = $action;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if ($notifiable->is_email_notification) {
            return ['mail', 'database'];
        } else {
            return ['database'];
        }
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        //Prepare the predefined config
        $user = User::where('id', $this->approve->original_creator_id)->with('clientEmployee')->with('iGlocalEmployee')->first();
        $approveContent = json_decode($this->approve->content, true);
        $lang = !empty($user->prefered_language) ? $user->prefered_language : 'en';
        $predefinedConfig = ["LANGUAGE" => $lang];

        switch ($this->action) {
            case Constant::INTERNAL_ACTIVATE_CLIENT:
                $client = Client::select('*')->where('id', $approveContent['id'])->first();

                $predefinedConfig = array_merge($predefinedConfig, [
                    'director_name' => $notifiable->name,
                    'client' => $client,
                    'creator' => $user
                ]);

                $subject = "[VPO] Approve – Create new employees/update client information";

                return $this->getMailMessage($subject, 'INTERNAL_ACTIVATE_CLIENT', $predefinedConfig, 'emails.clientNew');

            case Constant::INTERNAL_UPDATE_CLIENT:
                $client = Client::select('*')->where('id', $approveContent['id'])->first();

                $predefinedConfig = array_merge($predefinedConfig, [
                    'director_name' => $notifiable->name,
                    'client' => $client,
                    'creator' => $user
                ]);

                $subject = "[VPO] Approve – Create new employees/update client information";

                return $this->getMailMessage($subject, 'INTERNAL_UPDATE_CLIENT', $predefinedConfig, 'emails.clientUpdated');

            case Constant::INTERNAL_CONFIRM_UPDATED_CLIENT:
                $client = Client::select('*')->where('id', $approveContent['id'])->first();

                $approved_by = User::where('id', $this->approve->assignee_id)->first();

                $predefinedConfig = array_merge($predefinedConfig, [
                    'employee_name' => $notifiable->name,
                    'client' => $client,
                    'creator' => $user,
                    'approved_by' => $approved_by,
                ]);
                $subject = "[VPO] Approve - Confirm management approval";

                return $this->getMailMessage($subject, 'INTERNAL_CONFIRM_UPDATED_CLIENT', $predefinedConfig, 'emails.clientConfirmUpdated');

            case Constant::INTERNAL_APPROVED_CLIENT:
                $client = Client::select('*')->where('id', $approveContent['id'])->first();

                $approved_by = User::where('id', $this->approve->assignee_id)->first();

                $predefinedConfig = array_merge($predefinedConfig, [
                    'employee_name' => $notifiable->name,
                    'client' => $client,
                    'creator' => $user,
                    'approved_by' => $approved_by,
                ]);
                $subject = "[VPO] Approve - Confirm management approval";

                return $this->getMailMessage($subject, 'INTERNAL_APPROVED_CLIENT', $predefinedConfig, 'emails.clientApproved');

            case Constant::INTERNAL_DISAPPROVED_CLIENT:
                $client = Client::select('*')->where('id', $approveContent['id'])->first();

                $approved_by = User::where('id', $this->approve->assignee_id)->first();

                $predefinedConfig = array_merge($predefinedConfig, [
                    'employee_name' => $notifiable->name,
                    'client' => $client,
                    'creator' => $user,
                    'approved_by' => $approved_by,
                ]);
                $subject = "[VPO] Approve - Confirm management approval";

                return $this->getMailMessage($subject, 'INTERNAL_DISAPPROVED_CLIENT', $predefinedConfig);

            default:
                $approved_by = User::where('id', $this->approve->assignee_id)->with('clientEmployee')->with('iGlocalEmployee')->first();

                // Customer
                if (!$approved_by->is_internal) {
                    $creator = !$user->is_internal ? '[' . $user->clientEmployee['code'] . '] ' . $user->clientEmployee['full_name'] : '[VPO Team][' . $user->iGlocalEmployee['code'] . '] ' . $user->iGlocalEmployee['name'];
                    $predefinedConfig = array_merge($predefinedConfig, [
                        'creator' => $creator,
                        'assignee' => $approved_by->clientEmployee,
                        'content' => $approveContent,
                    ]);

                    if ($this->status == Constant::PROCESSING_STATUS) {

                        $lang = $approved_by->prefered_language;
                        app()->setlocale($lang);

                        // Check not null approve_comment
                        if (!empty($this->approve->approved_comment)) {
                            $predefinedConfig['comment'] = __('model.clients.reason') . ': ' . $this->approve->approved_comment;
                        }
                        $predefinedConfig = array_merge($predefinedConfig , [
                                'LANGUAGE' => $lang,
                                'type' => $this->trans($this->action, $this->action, $lang),
                                'detailButton' => $this->getUrlButton(false, true, $approveContent),
                                'status' => $this->getStatusTrans()
                            ]);

                        $subject = "[VPO] Approve - Confirm request approve";

                        return $this->getMailMessage($subject, 'CLIENT_REQUEST_APPROVE', $predefinedConfig, 'emails.clientRequestApprove');
                    } else {

                        $lang = $user->prefered_language;
                        app()->setlocale($lang);

                        // Check not null approve_comment
                        if (!empty($this->approve->approved_comment)) {
                            $predefinedConfig['comment'] = __('model.clients.reason') . ': ' . $this->approve->approved_comment;
                        }
                        $predefinedConfig = array_merge($predefinedConfig,[
                                'LANGUAGE' => $lang,
                                'type' => $this->trans($this->action, $this->action, $user->prefered_language),
                                'detailButton' => $this->getUrlButton(false, false, $approveContent),
                                'status' => $this->getStatusTrans()
                            ]);

                        $subject = "[VPO] Approve - The result of your approve request";

                        return $this->getMailMessage($subject, 'CLIENT_REQUEST_APPROVE_FINAL', $predefinedConfig, 'emails.clientRequestApproveFinal');
                    }
                } else {
                    $lang = $approved_by->prefered_language;
                    app()->setlocale($lang);

                    $status = $this->getStatusTrans();
                    $type = $this->trans($this->action, $this->action, $lang);
                    if ($this->status == Constant::PROCESSING_STATUS) {
                        $subject = "[VPO][IGLOCAL] Approve - Confirm request approve";

                        $predefinedConfig = array_merge($predefinedConfig, [
                            'status' => $status,
                            'creator' => $user->iGlocalEmployee,
                            'assignee' => $approved_by->iGlocalEmployee,
                            'type' => $type,
                            'content' => $approveContent,
                            'comment' => $this->approve->approved_comment
                        ]);

                        return $this->getMailMessage($subject, 'INTERNAL_REQUEST_APPROVE', $predefinedConfig, 'emails.internalRequestApprove');
                    } else {
                        $subject = "[VPO][IGLOCAL] Approve - The result of your approve request";

                        $predefinedConfig = array_merge($predefinedConfig, [
                            'status' => $status,
                            'creator' => $user->iGlocalEmployee,
                            'assignee' => $approved_by->iGlocalEmployee,
                            'type' => $type,
                            'content' => $approveContent,
                            'comment' => $this->approve->approved_comment
                        ]);

                        return $this->getMailMessage($subject, 'INTERNAL_REQUEST_APPROVE_FINAL', $predefinedConfig, 'emails.internalRequestApproveFinal');
                    }
                }
        }

    }

    public function getUrlButton($isInternal, $is_manager, $content = []): string
    {
        $type = $this->approve->type;
        if ($isInternal) {
            return "/yeu-cau-duyet";
        } else {
            $url = config('app.customer_url');

            if ($is_manager) {
                $url .= '/yeu-cau-duyet?id=' . $this->approve->id;
            } else {
                // Not need param id
                if ($type == 'CLIENT_REQUEST_TIMESHEET_SHIFT' || $type == 'CLIENT_REQUEST_PAYROLL') {
                    if ($type == 'CLIENT_REQUEST_TIMESHEET_SHIFT') {
                        $url .= "/quan-ly-danh-sach-ca";
                    }
                    if ($type == 'CLIENT_REQUEST_PAYROLL') {
                        $url .= '/bang-luong/' . $content['id'] . '/chi-tiet';
                    }

                } else {
                    if ($type == 'CLIENT_REQUEST_OT') {
                        $url .= "/dang-ky-cong-so-ot";
                    } elseif ($type == 'CLIENT_REQUEST_CONG_TAC' || $type == 'CLIENT_REQUEST_ROAD_TRANSPORTATION' || $type == 'CLIENT_REQUEST_AIRLINE_TRANSPORTATION') {
                        $url .= "/dang-ky-cong-so-cong-tac";
                    } elseif ($type == 'CLIENT_REQUEST_OFF') {
                        $url .= "/dang-ky-cong-so-nghi-phep";
                    } elseif (in_array($type, Constant::LIST_TYPE_ADJUST_HOURS)) {
                        $url .= "/dieu-chinh-gio-lam-thuc-te";
                    }
                    $url .= '?id=' . $this->approve->target_id;
                }
            }
        }

        $detail = __('model.buttons.detail');

        return "<a target=\"_blank\" href=\"" . $url . "\" class=\"button button-primary\" style=\"font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #fff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;\">$detail</a>";
    }

    private function getStatusTrans()
    {
            switch ($this->status) {
                case 'processing':
                    $status = __('status_processing');
                    break;
                case 'approved':
                    $status = __('model.clients.approved');
                    break;
                default:
                    $status = __('model.clients.rejected');
                    break;
            }

        return $status;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toDatabase(): array
    {

        switch ($this->action) {
            case Constant::INTERNAL_ACTIVATE_CLIENT:
            case Constant::INTERNAL_UPDATE_CLIENT:
            case Constant::INTERNAL_ACTIVATE_OR_UPDATE_CLIENT:

                $clientContent = json_decode($this->approve->content, true);
                $client = Client::select('*')->where('id', $clientContent['id'])->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.internal_active_or_update_client',
                        'params' => [
                            'clientName' => $client->company_name,
                            'clientCode' => $client->code,
                        ]
                    ],
                    'route' => '/quan-ly-khach-hang-moi',
                ];

            case Constant::INTERNAL_CONFIRM_UPDATED_CLIENT:

                $clientContent = json_decode($this->approve->content, true);
                $client = Client::select('*')->where('id', $clientContent['id'])->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.internal_confirm_update_client',
                        'params' => [
                            'clientName' => $client->company_name,
                            'clientCode' => $client->code,
                        ]
                    ],
                    'route' => '/khach-hang/tong-quan/' . $client->id,
                ];

            case Constant::INTERNAL_APPROVED_CLIENT:

                $clientContent = json_decode($this->approve->content, true);
                $client = Client::select('*')->where('id', $clientContent['id'])->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.internal_approved_client',
                        'params' => [
                            'clientName' => $client->company_name,
                            'clientCode' => $client->code,
                        ]
                    ],
                    'route' => '/khach-hang/tong-quan/' . $client->id,
                ];

            case Constant::INTERNAL_DISAPPROVED_CLIENT:

                $clientContent = json_decode($this->approve->content, true);
                $client = Client::select('*')->where('id', $clientContent['id'])->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.internal_disapproved_client',
                        'params' => [
                            'clientName' => $client->company_name,
                            'clientCode' => $client->code,
                        ]
                    ],
                    'route' => '/khach-hang/tong-quan/' . $client->id,
                ];

            case Constant::CLIENT_UPDATE_EMPLOYEE_OTHERS:

                $user = User::where('id', $this->approve->creator_id)->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.client_update_employee',
                        'params' => ['employeeName' => $user->name]
                    ],
                    'route' => '/yeu-cau-thay-doi-thong-tin',
                ];

            case Constant::CLIENT_REQUEST_PAYROLL:
                $requestContent = json_decode($this->approve->content, true);
                $calculationSheet = CalculationSheet::select('*')->where('id', $requestContent['id'])->first();

                return [
                    'type' => 'approve',
                    'messages' => [
                        'trans' => 'notifications.approve.client_request_payroll',
                        'params' => [
                            'calculationSheetName' => $calculationSheet->name
                        ]
                    ],
                    'route' => 'yeu-cau-duyet?id=' . $this->approve->id
                ];

            default:
                $user = User::where('id', $this->approve->original_creator_id)->with('clientEmployee', 'iGlocalEmployee')->first();
                $approved_by = User::where('id', $this->approve->assignee_id)->with('clientEmployee', 'iGlocalEmployee')->first();
                if ($approved_by) {
                    $lang = $approved_by->prefered_language;
//                    app()->setlocale($lang);
                    $status = $this->getStatusTrans();
                    $type = $this->trans($this->action, $this->action, $approved_by->prefered_language);

                    // Require is only send url by email not send to database
                    if (!$approved_by->is_internal) {

                        $creator = '[' . $user->clientEmployee['code'] . ']' . $user->clientEmployee['full_name'];

                        return [
                            'type' => 'approve',
                            'messages' => [
                                'trans' => 'notifications.request_approve',
                                'params' => [
                                    'type' => $type,
                                    'status' => $status,
                                    'creator' => $creator,
                                    'assignee' => $approved_by->clientEmployee->full_name
                                ]
                            ],
                            'route' => '/yeu-cau-duyet?id=' . $this->approve->id,
                        ];

                    } else {
                        $creator = '[' . $user->iGlocalEmployee['code'] . ']' . $user->iGlocalEmployee['name'];

                        return [
                            'type' => 'approve',
                            'messages' => [
                                'trans' => 'notifications.request_approve',
                                'params' => [
                                    'type' => $type,
                                    'status' => $status,
                                    'creator' => $creator,
                                    'assignee' => $approved_by->iGlocalEmployee->name
                                ]
                            ],
                            'route' => '/yeu-cau-duyet',
                        ];
                    }
                } else {
                    if (!$user->is_internal) {
                        $lang = $user->prefered_language;
//                        app()->setlocale($lang);

                        return [
                            'type' => 'approve',
                            'messages' => [
                                'trans' => 'notifications.request_approve',
                                'params' => [
                                    'type' => __('model.clients.approved'),
                                    'status' => $this->trans($this->action, $this->action, $lang),
                                    'creator' => '[' . $user->clientEmployee['code'] . ']' . $user->clientEmployee['full_name'],
                                    'assignee' => 'System'
                                ]
                            ],
                            'route' => '/dang-ky-cong-so-ot?id=' . $this->approve->id,
                        ];
                    }

                }
                break;
        }

    }
}
