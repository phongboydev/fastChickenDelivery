<?php

namespace App\Jobs;

use App\Jobs\PushUpdatedApproveNotificationJob;
use App\Models\WorkTimeRegisterPeriod;
use App\Support\Constant;
use App\Support\WorktimeRegisterHelper;
use App\Support\WorkTimeRegisterPeriodHelper;
use App\User;
use App\Models\Approve;
use App\Models\ApproveFlow;
use App\Models\ApproveFlowUser;

use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\ApproveNotification;
use App\Exceptions\CustomException;
use App\Models\ClientWorkflowSetting;
use Exception;

class ConfirmApproveJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $action;
    protected $approveId;
    protected $comment;
    protected $creatorId;
    protected $reviewerId;
    protected $nextStep;

    /**
     * Create a new job instance.
     *
     * @param SurveyJob           $job
     * @param SurveyJobSubmission $submission
     * @param array               $subjects
     * @param array               $htmls
     * @param string|null         $emailOverride
     */
    public function __construct($action, $approveId, $comment, $creatorId, $reviewerId, $nextStep = null)
    {
        $this->action = $action;
        $this->approveId = $approveId;
        $this->comment = $comment;
        $this->creatorId = $creatorId;
        $this->reviewerId = $reviewerId;
        $this->nextStep = $nextStep;
    }

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->approveId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $action = $this->action;
        $approveId = $this->approveId;

        try {
            $approve = Approve::select('*')->where('id', $approveId)
                ->whereNull('approved_at')
                ->whereNull('declined_at')->first();

            if (!$approve) return;

            logger('ConfirmApproveJob@handle client_id: ' . $approve->client_id, [$action, $approve->id, $this->reviewerId, $approve->processing_state]);

            switch ($action) {
                case 'accept':
                    $this->handleAccept($approve);
                    break;
                case 'switch':
                    $this->handleSwitch($approve);
                    break;
                default:
                    $this->handleDecline($approve);
                    break;
            }
        } catch (Exception $e) {

            $approve = Approve::where('id', $approveId)->first();

            $approve->approved_at = null;
            $approve->processing_state = 'fail';
            $approve->processing_error = $e->getMessage();
            $approve->save();

            logger()->error("ConfirmApproveJob {$this->approveId} error: " . $e->getMessage() . ' at line ' . $e->getLine());
        }
    }

    protected function handleAccept($approve)
    {
        $flowName = $approve->flow_type ?? $approve->type;

        $clientEmployeeGroupId = $approve->client_employee_group_id;

        $maxStep = ApproveFlow::where('client_id', $approve->client_id)
            ->where('flow_name', $flowName)
            ->where('group_id', $clientEmployeeGroupId)->max('step');

        $approve->processing_error = null;
        $approve->processing_state = 'complete';

        logger('ConfirmApproveJob@maxStep client_id: ' . $approve->client_id, [$approve->id, $maxStep, $approve->step, $approve->processing_state]);

        if ($approve->step < $maxStep) {

            if (!$this->reviewerId) {
                $approve->approved_at = null;
                $approve->processing_state = 'fail';
                $approve->processing_error = "Reviewer id = 0";
                $approve->save();
                return;
            }

            $reviewerId = $this->reviewerId;

            // Kiểm tra chức năng bẻ luồng OT - Duyệt theo từng bước
            $clientWorkflowSetting =  ClientWorkflowSetting::where('client_id', $approve->client_id)->first(['advanced_approval_flow']);
            if ($clientWorkflowSetting && $clientWorkflowSetting->advanced_approval_flow && in_array($approve->type, Constant::TYPE_ADVANCED_APPROVE)) {
                $advanced_approval_flow = true;
            } else {
                $advanced_approval_flow = false;
            }

            $approveFlowUser = ApproveFlowUser::where('user_id', $reviewerId)
                ->with('approveFlow')
                ->whereHas('approveFlow', function ($query) use ($flowName, $clientEmployeeGroupId, $advanced_approval_flow) {
                    $query->where('flow_name', $flowName)
                        ->where('group_id', $clientEmployeeGroupId);
                    if ($advanced_approval_flow) {
                        $query->where('step', $this->nextStep);
                    }
                })->get();


            $approve->approved_at = Carbon::now()->format('Y-m-d H:i:s');

            if ($approveFlowUser->isNotEmpty()) {

                $sortedApproveFlow = $approveFlowUser->sortBy(function ($item, $key) {

                    return $item->toArray()['approve_flow']['step'];
                });

                $approveFlow = $sortedApproveFlow->values()->last()->toArray();

                $targetId = $approve->target_id;

                $step = $approveFlow['approve_flow']['step'];

                $approveNext = new Approve();
                $approveNext->fill([
                    'client_id' => $approve->client_id,
                    'type' => $approve->type,
                    'content' => $approve->content,
                    'step' => $step,
                    'target_type' => $approve->target_type,
                    'target_id' => $targetId,
                    'approve_group_id' => $approve->approve_group_id,
                    'client_employee_group_id' => $approve->client_employee_group_id,
                    'creator_id' => $this->creatorId,
                    'original_creator_id' => $approve->original_creator_id,
                    'assignee_id' => $reviewerId,
                    'is_final_step' => 0,
                    'source' => $approve->source
                ]);
                $approve->save();
                $approveNext->save();

                // Clone Attachments
                if ($flowName === 'CLIENT_UPDATE_DEPENDENT') {
                    $mediaCollection = $approve->getMedia('Attachments');
                    $mediaCollection->each(function ($mediaItem) use ($approveNext) {
                        try {
                            $mediaItem->copy($approveNext, 'Attachments', 'minio');
                        } catch (Exception $e) {
                            // Retry the job up to 2 times
                            retry(2, function () use ($mediaItem, $approveNext) {
                                $mediaItem->copy($approveNext, 'Attachments', 'minio');
                            });
                        }
                    });
                }
            } else {
                $approve->approved_at = null;
                $approve->processing_state = 'fail';
                $approve->processing_error = "Reviewer $reviewerId , approveFlowUser $flowName , clientEmployeeGroupId $clientEmployeeGroupId";
                $approve->save();
            }
        } else {

            $approve->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $approve->save();
        }
    }

    protected function handleSwitch($approve)
    {
        logger('ConfirmApproveJob@handleSwitch client_id: ' . $approve->client_id, [$approve->id, $this->reviewerId]);

        if (!$this->reviewerId) {
            $approve->approved_at = null;
            $approve->processing_state = 'fail';
            $approve->processing_error = "Reviewer id = 0";
            $approve->save();
            return;
        }

        $approve->assignee_id = $this->reviewerId;
        $approve->processing_state = null;
        $approve->save();

        $reviewer = User::where('id', $this->reviewerId)->first();

        if (!empty($reviewer)) {
            dispatch(
                new PushUpdatedApproveNotificationJob($approve, 'switch')
            );
            dispatch(
                new PushNewApproveNotificationJob($approve)
            );
            $reviewer->notify(new ApproveNotification($approve, $approve->type));
        }
    }

    protected function handleDecline($approve)
    {
        logger('ConfirmApproveJob@handleDecline client_id: ' . $approve->client_id, [$approve->id]);

        $comment = $this->comment ? $this->comment : '';

        $approve->processing_error = null;
        $approve->processing_state = 'complete';
        $approve->approved_comment = $comment;
        $approve->declined_at = Carbon::now()->format('Y-m-d H:i:s');
        $approve->save();

        if ($approve->target) {
            $target = $approve->target;
            if (property_exists($approve->target, 'approved_comment')) {
                $target->approved_comment = $comment;
            }

            if ($approve->target_type == 'App\Models\PaymentRequest' && array_key_exists('status', $target->getAttributes())) {
                $target->status = 'declined';
            }

            if ($approve->target_type == 'App\Models\PaymentRequest' && array_key_exists('approved_comment', $target->getAttributes())) {
                $target->approved_comment = $comment;
            }

            $target->save();
        }

        // Cancellation approval pending
        WorkTimeRegisterPeriodHelper::updateCancellationApprovalPending($approve, false);
    }
}
