<?php

namespace App\GraphQL\Mutations;

use App\Models\Evaluation;
use App\Models\EvaluationGroup;
use App\Models\EvaluationUser;
use App\Models\LibraryQuestionAnswer;
use App\Models\PaymentRequestExportTemplate;
use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use App\Support\Constant;
use ErrorException;
use HttpException;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use \Maatwebsite\Excel\Validators\ValidationException as ValidationException;
use App\Exceptions\CustomException;
use App\Models\ClientEmployee;
use App\Models\ClientAppliedDocument;
use App\Models\ClientEmployeeContract;
use App\Models\ClientEmployeeForeignVisa;
use App\Models\ClientEmployeeForeignWorkpermit;
use App\Models\Comment;
use App\Models\ContractTemplate;
use App\Models\Contract;
use App\Models\JobboardApplication;
use App\Models\CalculationSheetExportTemplate;
use App\Models\CalculationSheet;
use App\Models\PayrollAccountantExportTemplate;
use App\Models\PaymentRequest;
use App\Models\Slider;
use App\Models\AssignmentTask;
use App\Models\DataImport;
use App\Models\DebitRequest;
use App\Models\ReportPit;
use App\Models\WorktimeRegister;
use App\Models\ClientEmployeeLocationHistory;
use App\Models\DebitHistory;
use App\Models\DataImportHistory;
use App\Models\TrainingSeminar;
use App\Models\SocialSecurityClaim;
use App\Models\SocialSecurityProfile;
use App\Models\SocialSecurityProfileRequest;
use App\Models\Approve;
use App\Models\ClientEmployeeDependent;
use App\Models\ClientEmployeeDependentApplication;
use App\Models\WebFeatureSlider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaMutator
{
    private function getModel($name, $id)
    {
        switch ($name) {
            case 'ClientEmployee': #get avatar
                return ClientEmployee::find($id);
                break;
            case 'ClientAppliedDocument': #
                return ClientAppliedDocument::authUserAccessible()->find($id);
                break;
            case 'Comment': #WARNING
                return Comment::find($id);
                break;
            case 'ContractTemplate':
                return ContractTemplate::find($id);
                break;
            case 'Contract': #
                return Contract::authUserAccessible()->find($id);
            case 'JobboardApplication':
                return JobboardApplication::find($id);
                break;
            case 'ClientEmployeeContract': #
                return ClientEmployeeContract::authUserAccessible()->find($id);
                break;
            case 'ClientEmployeeForeignVisa':
                return ClientEmployeeForeignVisa::find($id);
                break;
            case 'ClientEmployeeForeignWorkpermit':
                return ClientEmployeeForeignWorkpermit::find($id);
                break;
            case 'CalculationSheet': #
                return CalculationSheet::authUserAccessible(["advanced_permissions" => ['advanced-manage-payroll-list-export']])->find($id);
                break;
            case 'CalculationSheetExportTemplate':
                return CalculationSheetExportTemplate::find($id);
                break;
            case 'PaymentRequestExportTemplate':
                return PaymentRequestExportTemplate::find($id);
                break;
            case 'PayrollAccountantExportTemplate':
                return PayrollAccountantExportTemplate::find($id);
                break;
            case 'PaymentRequest': #
                return PaymentRequest::authUserAccessible()->find($id);
                break;
            case 'Slider':
                return Slider::authUserAccessible()->find($id);
                break;
            case 'AssignmentTask':
                return AssignmentTask::find($id);
                break;
            case 'DataImport': #
                return DataImport::authUserAccessible()->find($id);
                break;
            case 'ReportPIT': #
                return ReportPit::authUserAccessible(["advanced_permissions" => ['advanced-manage-payroll-list-export']])->find($id);
                break;
            case 'DebitRequest':
                return DebitRequest::find($id);
                break;
            case 'DebitHistory':
                return DebitHistory::find($id);
                break;
            case 'WorktimeRegister':
                return WorktimeRegister::find($id);
                break;
            case 'ClientEmployeeLocationHistory':
                return ClientEmployeeLocationHistory::find($id);
                break;
            case 'DataImportHistory': #
                $data = DataImportHistory::authUserAccessible()->find($id);
                if ($data && $data->type == "IMPORT_CLIENT_EMPLOYEE") {
                    $user = Auth::user();
                    $normalPermissions = ["manage-employee"];
                    $advancedPermissions = ["advanced-manage-employee-list-import", "advanced-manage-employee"];
                    if ($user->checkHavePermission($normalPermissions, $advancedPermissions, $user->getSettingAdvancedPermissionFlow())) {
                        return $data;
                    } else {
                        return null;
                    }
                } else {
                    return $data;
                }
                break;
            case 'TrainingSeminar':
                return TrainingSeminar::find($id);
                break;
            case 'SocialSecurityClaim': #
                return SocialSecurityClaim::authUserAccessible(["advanced_permissions" => ['advanced-manage-payroll-social-insurance']])->find($id);
                break;
            case 'SocialSecurityProfile': #
                return SocialSecurityProfile::authUserAccessible(["advanced_permissions" => ['advanced-manage-payroll-social-insurance']])->find($id);
                break;
            case 'SocialSecurityProfileRequest': #
                return SocialSecurityProfileRequest::authUserAccessible(["advanced_permissions" => ['advanced-manage-payroll-social-declaration']])->find($id);
                break;
            case 'EvaluationGroup':
                return EvaluationGroup::find($id);
                break;
            case 'EvaluationUser':
                return EvaluationUser::find($id);
                break;
            case 'Approve': #
                return Approve::find($id);
                break;
            case 'ClientEmployeeDependentApplication': #
                return ClientEmployeeDependentApplication::authUserAccessible()->find($id);
                break;
            case 'ClientEmployeeDependent': #
                return ClientEmployeeDependent::authUserAccessible()->find($id);
                break;
            case 'SupportTicket':
                return SupportTicket::find($id);
                break;
            case 'LibraryQuestionAnswer':
                return LibraryQuestionAnswer::find($id);
                break;
            case 'SupportTicketComment':
                return SupportTicketComment::find($id);
                break;
            case 'WebFeatureSlider':
                return WebFeatureSlider::authUserAccessible()->find($id);
                break;
        }

        return false;
    }

    public function uploadTemporaryMedia($root, array $args)
    {
        $rules = array(
            'file'      => 'required',
        );

        try {
            Validator::make($args, $rules);

            $f = preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($args['file']->getClientOriginalName(), PATHINFO_FILENAME))) . '-' . time();
            $fileName = $f . '.' . pathinfo($args['file']->getClientOriginalName(), PATHINFO_EXTENSION);
            $mime_type = $args['file']->getMimeType();
            $file_size = $args['file']->getSize();

            $path = Storage::disk('minio')->putFileAs('temp', $args['file'], $fileName);
            if (isset($args['model'])) {
                $this->checkExtensionFileBaseOnModel($args['model'], $mime_type);
            }

            $expired = Carbon::now()->addMinutes(config('app.media_temporary_time', 5));

            $url = Storage::temporaryUrl($path, $expired);

            return json_encode([
                'status' => 200,
                'name' => $fileName,
                'url' => $url,
                'path' => $path,
                'file_size' =>  $file_size,
                'mime_type' => $mime_type,
                'expired' => $expired
            ], 200);
        } catch (ValidationException $e) {

            throw new CustomException(
                'The given data was invalid.',
                'ValidationException'
            );
        } catch (ErrorException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ErrorException'
            );
        } catch (HttpException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'HttpException'
            );
        }
    }

    public function uploadMedia($root, array $args)
    {
        try {
            $f = preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($args['file']->getClientOriginalName(), PATHINFO_FILENAME))) . '-' . time();
            $fileName = $f . '.' . pathinfo($args['file']->getClientOriginalName(), PATHINFO_EXTENSION);

            $path = Storage::disk('minio')->putFileAs('temp', $args['file'], $fileName);

            $model = $this->getModel($args['model'], $args['id']);

            if (!empty($model)) {

                // TODO handle anonymous upload
                if (!$this->canUpload($model)) {
                    throw new CustomException(
                        'You have not permission upload',
                        'ValidationException'
                    );
                }
                if ($args['collection'] == 'avatar_hanet') {
                    $mediaItems = $model->getMedia($args['collection']);
                    if ($mediaItems) {
                        foreach ($mediaItems as $item) {
                            $item->delete();
                        }
                    }
                }

                $media = $model->addMediaFromDisk($path, 'minio')
                    ->storingConversionsOnDisk('minio')
                    ->toMediaCollection($args['collection'], 'minio');

                return $media;
            }
        } catch (ValidationException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ValidationException'
            );
        } catch (ErrorException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'ErrorException'
            );
        } catch (HttpException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'HttpException'
            );
        }
    }

    public function attachMedia($root, array $args)
    {

        $model = $this->getModel($args['model'], $args['id']);

        if (!empty($model)) {

            // TODO handle anonymous upload
            if (!$this->canUpload($model)) {
                throw new CustomException(
                    'You have not permission upload',
                    'ValidationException'
                );
            }

            $media = $model->addMediaFromDisk($args['path'], 'minio')
                ->storingConversionsOnDisk('minio')
                ->toMediaCollection($args['collection'], 'minio');
            $media->original_url = $media->getFullUrl();

            if (in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                $media->thumb_path = $this->getMediaPathAttribute() . $media->getPath('thumb');
            } else {
                $media->thumb_path = '';
            }

            return $media;
        }
    }

    public function attachMedias($root, array $args)
    {

        $model = $this->getModel($args['model'], $args['id']);
        $responses = [];

        if (!empty($model)) {

            // TODO handle anonymous upload
            if (!$this->canUpload($model)) {
                throw new CustomException(
                    'You have not permission upload',
                    'ValidationException'
                );
            }

            $pathData = $args['paths'];

            foreach ($pathData as $pData) {

                $mediaItem  = $model->addMediaFromDisk($pData['path'], 'minio')->storingConversionsOnDisk('minio')->toMediaCollection($args['collection'], 'minio');

                if (isset($pData['description'])) {
                    $mediaItem->setCustomProperty('description', $pData['description']);
                    $mediaItem->save();
                }

                $responses[] = $mediaItem;
            }
        }

        return $responses;
    }

    public function detachMedia($root, array $args)
    {
        $model = $this->getModel($args['model'], $args['id']);

        if (empty($model)) return 'fail';

        if (!$this->canUpload($model)) {
            throw new CustomException(
                'You have not permission upload',
                'ValidationException'
            );
        }

        $mediaItems = $model->getMedia($args['collection']);

        if ($mediaItems) {

            $mediaIds = [];

            if (isset($args['media_id']) && $args['media_id']) {
                $mediaIds[] = $args['media_id'];
            }

            if (isset($args['media_ids']) && is_array($args['media_ids'])) {
                $mediaIds = array_merge($mediaIds, $args['media_ids']);
            }

            $items = $mediaItems->whereIn('id', $mediaIds)->all();

            if ($items) {
                foreach ($items as $item) {
                    $item->delete();
                }
            }

            return 'ok';
        }

        return 'fail';
    }

    public function updateDescription($root, array $args)
    {
        $mediaItem = Media::find($args['id']);

        $mediaItem->setCustomProperty('description', $args['content']);

        $mediaItem->save();

        return 'ok';
    }

    public function clearMedia($root, array $args)
    {
        $model = $this->getModel($args['model'], $args['id']);

        if (empty($model)) return 'fail';

        if ($args['collection'] == 'avatar_hanet') {
            $user = Auth::user();
            $checkPermission = !$user->hasPermissionTo("manage-camera-checkin");
            if ($checkPermission) {
                throw new CustomException(
                    __("error.permission"),
                    'ValidationException'
                );
            }
        }

        $mediaItems = $model->getMedia($args['collection']);

        if ($mediaItems) {
            foreach ($mediaItems as $item) {
                $item->delete();
            }
        }

        return 'ok';
    }

    public function getDownloadPath($root, array $args)
    {
        $model = $this->getModel($args['model'], $args['id']);

        return !empty($model) ? $model->path : '';
    }

    private function canUpload($model)
    {

        $PolicyClass = 'App\\Policies\\' . class_basename($model) . 'Policy';

        if (class_exists($PolicyClass)) {

            $policyClass = new $PolicyClass;

            if (method_exists($policyClass, 'upload')) {

                return $policyClass->upload(Auth::user(), $model);
            } else {
                return false;
            }
        }

        return false;
    }

    private function getMediaPathAttribute()
    {
        return env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/';
    }

    private function checkExtensionFileBaseOnModel($model, $typeMiMe)
    {
        $isAllowTypeFile = true;
        $listAcceptExtension = '';
        switch ($model) {
            case 'EvaluationGroup':
            case 'Evaluation':
            case 'EvaluationUser':
                $listAllowTypeExtensionFile = array_merge(Constant::TYPE_MIME_TYPE_FILE['excel'], Constant::TYPE_MIME_TYPE_FILE['word']);
                if (!in_array($typeMiMe, $listAllowTypeExtensionFile)) {
                    $listAcceptExtension = 'excel,word';
                    $isAllowTypeFile = false;
                    break;
                }
        }
        if (!$isAllowTypeFile) {
            throw new CustomException(
                __("errror.incorrect_mime_type_extension_file") . $listAcceptExtension,
                'ValidationException'
            );
        }
    }
}
