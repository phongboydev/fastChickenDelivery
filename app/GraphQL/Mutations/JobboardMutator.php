<?php

namespace App\GraphQL\Mutations;

use ErrorException;
use HttpException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Exceptions\CustomException;

use App\Models\JobboardApplication;
use App\Models\Jobboard;
use App\Support\MediaTrait;
use App\Jobs\SendJobboardApplicationRejectEmail;
use Maatwebsite\Excel\Facades\Excel;
use \Maatwebsite\Excel\Validators\ValidationException as ValidationException;

use App\Exports\JobboardApplicationExport;
use DateInterval;
use DateTime;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException as ExceptionHttpException;

class JobboardMutator
{

    protected $key = "ZThlZDYyYmVjN2EyNmRiNWI5OW";

    public function exportJobboardApplication($root, array $args)
    {
        $jobboard_job_id = $args['jobboard_job_id'];

        $templateExport = 'JobboardTemplate/jobboard_application_template.xlsx';

        $time = time();

        $fileName = $jobboard_job_id . '/' . "jobboard_application_{$time}.xlsx";

        $pathFile = 'Jobboard/' . $fileName;

        $errors = false;

        try {

            if (!Storage::disk('local')->missing($templateExport)) {

                Excel::store((new JobboardApplicationExport($jobboard_job_id, $templateExport, $pathFile)), $pathFile, 'minio');

            } else {
                throw new CustomException(
                    'File template báo cáo bị mất',
                    'ValidationException'
                );
            }

        } catch (CustomException $e) {
            $errors = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = [
                'error' => true,
                'message' => 'File template bị lỗi'
            ];
        }

        if( $errors )
        {
            return json_encode($errors);
        }else{

            $response = [
                'error' => false,
                'name' => $fileName,
                // 'file' => env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/' . $pathFile
                'file' => Storage::temporaryUrl($pathFile, Carbon::now()->addMinutes(config('app.media_temporary_time', 5)))
            ];

            return json_encode($response);
        }
    }

    public function genNonceToken($root, array $args) {
        $nonce = random_bytes(32);

        $expires = new DateTime();
        date_add($expires, new DateInterval("PT15M"));

        $message = json_encode([
            'nonce' => base64_encode($nonce),
            'expires' => $expires->format('Y-m-d\TH:i:s')
        ]);

        return base64_encode(
            hash_hmac('sha256', $message, $this->key, true) . $message
        );
    }

    private function validToken($input) {
        $decoded = base64_decode($input);
        if ($decoded === false) {
            throw new Exception("Encoding error");
        }
        $mac = mb_substr($decoded, 0, 32, '8bit');

        $message = mb_substr($decoded, 32, null, '8bit');

        $calc = hash_hmac('sha256', $message, $this->key, true);

        if (!hash_equals($calc, $mac)) {
            throw new Exception("Invalid MAC");
        }
        $message = json_decode($message);
        $currTime = new DateTime('NOW');
        $expireTime = new DateTime($message->expires);
        if ($currTime > $expireTime) {
            throw new CustomException(
                'Waiting time has expired<br> Please reload the page to continue',
                'TokenExpired'
            );
        }
        $nonce = $message->nonce;
        logger()->debug($nonce);
    }

    private function getModel($name, $id)
    {
        switch($name) {
            case 'JobboardApplication':
                return JobboardApplication::find($id);
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

            $token = $args['token'];
            $this->validToken($token);

            $mime_type = $args['file']->getMimeType();


            if ($mime_type != "application/pdf" && $mime_type != "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
                throw new CustomException(
                    'The given data was invalid.',
                    'ErrorException'
                );
            }


            $f = preg_replace( '/[^a-z0-9]+/', '-', strtolower(pathinfo($args['file']->getClientOriginalName(), PATHINFO_FILENAME)) ) . '-' . time();
            $fileName = $f . '.' . pathinfo($args['file']->getClientOriginalName(), PATHINFO_EXTENSION );

            $path = Storage::disk('minio')->putFileAs('temp', $args['file'], $fileName);

            $url = Storage::temporaryUrl(
                $path, Carbon::now()->addMinutes(config('app.media_temporary_time', 5))
            );

            return json_encode([
                'status' => 200,
                'name' => $fileName,
                'url' => $url,
                'path' => $path,
                'mime_type' => $mime_type
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
        } catch (ExceptionHttpException $e) {
            throw new CustomException(
                'The given data was invalid.',
                'HttpException'
            );
        }
    }

    public function attachMedia($root, array $args)
    {

        $model = $this->getModel($args['model'], $args['id']);


        $token = $args['token'];
        $this->validToken($token);


        if(!empty($model)){


            $media = $model->addMediaFromDisk($args['path'], 'minio')
                    ->storingConversionsOnDisk('minio')
                    ->toMediaCollection($args['collection'], 'minio');
            $media->original_url = $media->getFullUrl();

            if(in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/gif'])){
                $media->thumb_path = $this->getMediaPathAttribute() . $media->getPath('thumb');
            }else{
                $media->thumb_path = '';
            }

            return $media;
        }
    }

    public function detachMedia($root, array $args)
    {
        $model = $this->getModel($args['model'], $args['id']);


        $token = $args['token'];
        $this->validToken($token);

        if(empty($model)) return 'fail';

        $mediaItems = $model->getMedia($args['collection']);

        if($mediaItems) {

            $mediaIds = [];

            if (isset($args['media_id']) && $args['media_id']) {
                $mediaIds[] = $args['media_id'];
            }

            if (isset($args['media_ids']) && is_array($args['media_ids'])) {
                $mediaIds = array_merge($mediaIds, $args['media_ids']);
            }

            $items = $mediaItems->whereIn('id', $mediaIds)->all();

            if($items) {
                foreach($items as $item) {
                    $item->delete();
                }
            }

            return 'ok';
        }

        return 'fail';
    }

    private function canUpload( $model ) {

        $PolicyClass = 'App\\Policies\\' . class_basename($model) . 'Policy';

        if(class_exists($PolicyClass)) {

            $policyClass = new $PolicyClass;

            if(method_exists($policyClass, 'upload')) {

                return $policyClass->upload(Auth::user(), $model);
            }else{
                return false;
            }
        }

        return false;
    }

    private function getMediaPathAttribute()
    {
        return env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/';
    }
    public function changeSentMailStatus($root, array $args)
    {
        try {
            // Dispatch the job
            SendJobboardApplicationRejectEmail::dispatch($args['id'], $args['is_sent']);
            return true;
        } catch (\Exception $e) {
            // Log the exception
            \Log::error('Failed to dispatch email job', ['exception' => $e]);
            return false;
        }
    }
}
