<?php

namespace App\Observers;

use App\Models\ClientEmployeeDependentRequest;
use App\Exceptions\HumanErrorException;
use App\Models\IglocalAssignment;
use App\Models\ClientEmployeeDependent;
use App\Notifications\DependentNotification;

class ClientEmployeeDependentRequestObserver
{
    /**
     * Handle the ClientEmployeeDependentRequest "created" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function created(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        // Sent Notification to Internal
        $assignmentUsers = IglocalAssignment::where(['client_id' => $clientEmployeeDependentRequest->client_id])
            ->with(["user"])
            ->has("user")
            ->get();

        $assignmentUsers->each(
            function (IglocalAssignment $assignmentUser) use ($clientEmployeeDependentRequest) {
                try {
                    $assignmentUser->user->notify(new DependentNotification('internal', $clientEmployeeDependentRequest));
                } catch (\Exception $e) {
                    logger()->error(__METHOD__ . ' ' . $e->getMessage());
                }
            }
        );
    }

    /**
     * Handle the ClientEmployeeDependentRequest "updating" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function updating(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        $originalProcessing = $clientEmployeeDependentRequest->getOriginal('processing');
        $isInternalUser = auth()->user()->is_internal;

        // Kiểm tra trạng thái
        if ($originalProcessing !== 'result_sent' && $isInternalUser) {
            $applications = $clientEmployeeDependentRequest->applications;

            $pendingApplications = $applications->whereNull('status');
            $processingStatus = $clientEmployeeDependentRequest->processing;

            if ($processingStatus === 'result_sent' && $pendingApplications->isNotEmpty()) {
                throw new HumanErrorException(__('error.dependent.application.not_yet_approved'));
            }

            $newProcessingStatus = ($processingStatus === 'prossesing') ? 'in_progress' : $processingStatus;

            $applications->each(function ($application) use ($newProcessingStatus) {
                $application->update([
                    'processing' => $newProcessingStatus
                ]);
            });
        } else {
            throw new HumanErrorException(__('model.notifications.unsuccess'));
        }
    }

    /**
     * Handle the ClientEmployeeDependentRequest "updated" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */

    public function updated(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        if ($clientEmployeeDependentRequest->processing === 'result_sent') {
            $clientEmployeeDependentRequest->applications
                ->each(function ($application) {

                    $data = [
                        'client_employee_id' => $application->client_employee_id,
                        'name_dependents' => $application->name_dependents,
                        'status' => $application->status,
                        'creator_id' => $application->creator_id,
                        'id' => $application->id,
                    ];

                    if ($application->status === 'approved') {

                        if ($application->client_employee_dependent_id) {
                            $clientEmployeeDependent = ClientEmployeeDependent::find($application->client_employee_dependent_id);
                            $clientEmployeeDependent->to_date = $application->to_date;
                            $clientEmployeeDependent->save();
                        } else {
                            $clientEmployeeDependent = ClientEmployeeDependent::create([
                                'client_employee_id' => $application->client_employee_id,
                                'client_employee_dependent_id' => $application->client_employee_dependent_id,
                                'name_dependents' => $application->name_dependents,
                                'tax_code' => $application->tax_code,
                                'identification_number' => $application->identification_number,
                                'date_of_birth' => $application->date_of_birth,
                                'nationality' => $application->nationality,
                                'country_code' => $application->country_code,
                                'relationship_code' => $application->relationship_code,
                                'tax_office_province_id' => $application->tax_office_province_id,
                                'tax_office_district_id' => $application->tax_office_district_id,
                                'tax_office_ward_id' => $application->tax_office_ward_id,
                                'dob_info_num' => $application->dob_info_num,
                                'dob_info_book_num' => $application->dob_info_book_num,
                                'reg_type' => $application->reg_type,
                                'from_date' => $application->from_date,
                                'to_date' => $application->to_date,
                            ]);
                            $application->update(['client_employee_dependent_id' => $clientEmployeeDependent->id]);
                        }

                        // Copy Media
                        $application->media->each(function ($mediaItem) use ($clientEmployeeDependent) {
                            $mediaItem->copy($clientEmployeeDependent, 'Attachments', 'minio');
                        });

                        // Sent Notification to Staff
                        $notiToStaff = new DependentNotification('staff', $data);
                        $clientEmployeeDependent->clientEmployee->user->notify($notiToStaff);
                    }

                    // Sent Notification to Creator
                    $notiToCreator = new DependentNotification('staff', $data);
                    $application->creator->user->notify($notiToCreator);
                });

            // Sent Notification to Admin
            $notiToAdmin = new DependentNotification('admin', $clientEmployeeDependentRequest);
            $clientEmployeeDependentRequest->creator->notify($notiToAdmin);
        }
    }

    /**
     * Handle the ClientEmployeeDependentRequest "deleting" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function deleting(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        if ($clientEmployeeDependentRequest->isForceDeleting()) {
            $clientEmployeeDependentRequest->applications->each->update([
                'processing' => 'new'
            ]);
        }
    }

    /**
     * Handle the ClientEmployeeDependentRequest "deleted" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function deleted(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
    }

    /**
     * Handle the ClientEmployeeDependentRequest "restored" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function restored(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
        $clientEmployeeDependentRequest->dependentRequestApplicationLink->restore();
    }

    /**
     * Handle the ClientEmployeeDependentRequest "force deleted" event.
     *
     * @param  \App\Models\ClientEmployeeDependentRequest  $clientEmployeeDependentRequest
     * @return void
     */
    public function forceDeleted(ClientEmployeeDependentRequest $clientEmployeeDependentRequest)
    {
    }
}
