<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class SpatieMediaCustomDirectoryHelper implements PathGenerator
{
    /*
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        $model = $media->model;
        if (isset($model)) {

            switch ($media->model_type) {
                case 'App\Models\ClientEmployee':
                    return 'Avatar/' . $media->id . '/';
                case 'App\Models\ClientAppliedDocument':
                    return 'ClientAppliedDocument/' . $media->id . '/';
                case 'App\Models\Comment':
                    return 'Comment/' . $media->id . '/';
                case 'App\Models\ContractTemplate':
                    return 'ContractTemplate/' . $media->id . '/';
                case 'App\Models\Contract':
                    return 'Contract/' . $media->id . '/';
                case 'App\Models\ClientEmployeeContract':
                    return 'ClientEmployeeContract/' . $media->id . '/';
                case 'App\Models\JobboardApplication':
                    return 'JobboardApplication/' . $media->id . '/';
                case 'App\Models\ReportPayroll':
                    return 'ReportPayroll/' . $media->id . '/';
                case 'App\Models\ReportPit':
                    return 'ReportPit/' . $media->id . '/';
                case 'App\Models\CalculationSheet':
                    return 'CalculationSheet/' . $media->id . '/';
                case 'App\Models\CalculationSheetExportTemplate':
                    return 'CalculationSheetExportTemplate/' . $media->id . '/';
                case 'App\Models\PayrollAccountantExportTemplate':
                    return 'PayrollAccountantExportTemplate/' . $media->id . '/';
                case 'App\Models\TrainingSeminar':
                    return 'TrainingSeminar/' . $media->id . '/';
                case 'App\Models\ClientEmployeeTrainingSeminar':
                    return 'ClientEmployeeTrainingSeminar/' . $media->id . '/';
                case 'App\Models\ClientCameraCheckinDevice':
                    return 'ClientCameraCheckinDevice/' . $media->id . '/';
                case 'App\Models\SocialSecurityProfile':
                    return 'SocialSecurityProfile/' . $media->id . '/';
                case 'App\Models\DataImport':
                    return 'DataImport/' . $media->id . '/';
                case 'App\SocialSecurityProfileRequest':
                    return 'SocialSecurityProfileRequest/' . $media->id . '/';
                case 'App\Models\WorktimeRegister':
                    return 'WorktimeRegister/' . $media->id . '/';
                case 'App\Models\DataImportHistory':
                    return 'DataImportHistory/' . $media->id . '/';
                case 'App\Models\ClientEmployeeForeignWorkpermit':
                    return 'ClientEmployeeForeignWorkpermit/' . $media->id . '/';
                case 'App\Models\ClientEmployeeForeignVisa':
                    return 'ClientEmployeeForeignVisa/' . $media->id . '/';
                case 'App\Models\TimeSheetEmployeeImport':
                    return 'TimeSheetEmployeeImport/' . $media->id . '/';
                case 'App\Models\Approve':
                    return 'Approve/' . $media->id . '/';
                case 'App\Models\ClientEmployeeDependentApplication':
                    return 'ClientEmployeeDependentApplication/' . $media->id . '/';
                case 'App\Models\ClientEmployeeDependent':
                    return 'ClientEmployeeDependent/' . $media->id . '/';
                case 'App\Models\WebFeatureSlider':
                    return 'WebFeatureSlider/' . $media->id . '/';
                default:
                    return class_basename($media->model_type);
            }
        } else {
            return 'NoModelSpatie/' . $media->id . '/';
        }
    }
    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     * @return string
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'c/';
    }

    /*
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    /*
     * Get a unique base path for the given media.
     */
    protected function getBasePath(Media $media): string
    {
        return $media->getKey();
    }
}
