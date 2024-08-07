<?php

namespace App\GraphQL\Mutations;

use App\Exceptions\CustomException;
use App\Models\CalculationSheetExportTemplate;
use App\Support\MediaTrait;
use Illuminate\Support\Facades\Auth;
use ErrorException;
use HttpException;
use Illuminate\Support\Facades\Storage;

class CalculationSheetExportTemplateMutator
{
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed $root
     * @param  mixed[] $args
     * @return string|null
     */
    public function createTemplate($root, array $args)
    {

        try {

            $user = Auth::user();
            
            $client_id = !$user->isInternalUser() ? $user->client_id : $args['client_id'] ?? '';

            $path = Storage::disk('minio')->put('CalculationSheetExportTemplate', $args['file']);

            $calculationSheetExportTemplate = CalculationSheetExportTemplate::create([
                'name' => $args['name'],
                'file_name' => $path,
                'client_id' => $client_id
            ]);

            return $calculationSheetExportTemplate;

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

    public function updateTemplate($root, array $args)
    {

        try {

            $calculationSheetExportTemplate = CalculationSheetExportTemplate::query()
                                                                            ->where('id', $args['id'])
                                                                            ->first();

            $path = $calculationSheetExportTemplate->file_name;
            if( $args['file'] ) {
                Storage::disk('minio')->delete($calculationSheetExportTemplate->file_name);
                $path = Storage::disk('minio')->put('CalculationSheetExportTemplate', $args['file']);
            }

            $calculationSheetExportTemplate->fill([
                'name' => $args['name'],
                'file_name' => $path
            ]);
            $calculationSheetExportTemplate->save();
            return $calculationSheetExportTemplate;

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

    public function deleteTemplate($root, array $args)
    {

        try {

            $user = Auth::user();

            $resCalculationSheetExportTemplate = CalculationSheetExportTemplate::find($args['id']);

            if( $user->isInternalUser() || ($user->client_id == $resCalculationSheetExportTemplate->client_id) )
            {

                $calculationSheetExportTemplate = CalculationSheetExportTemplate::select('file_name')->where('id', $args['id']);

                Storage::disk('minio')->delete($calculationSheetExportTemplate->first()->file_name);

                $calculationSheetExportTemplate->delete();
            }

            return $resCalculationSheetExportTemplate;

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
}
