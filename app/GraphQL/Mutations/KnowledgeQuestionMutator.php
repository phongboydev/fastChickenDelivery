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

use App\Models\KnowledgeQuestion;
class KnowledgeQuestionMutator
{
    public function upload($root, array $args)
    {
        $rules = array(
            'file'      => 'required|mimes:jpg,gif,png',
        );

        try {
            Validator::make($args, $rules);

            $path = Storage::disk('minio')->put('KnowledgeQuestion', $args['file']);

            $full_path = env('MINIO_URL') . '/' . env('MINIO_BUCKET') . '/' . $path;

            return json_encode(['status' => 200, 'full_path' => $full_path, 'path' => $path], 200);

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