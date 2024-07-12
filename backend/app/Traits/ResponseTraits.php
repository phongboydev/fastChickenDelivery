<?php

namespace App\Traits;

use App\Exceptions\RoleAdminException;
use App\Http\Controllers\AuthController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait ResponseTraits
{
    /**
     * Response after login
     *
     * @param $status
     * @param $message
     * @param $tokenResult
     * @return JsonResponse
     */
    public function responseAuth($status, $message, $tokenResult = null)
    {
        return response()->json([
            'status_code' => $status,
            'message' => $message,
            'access_token' => $tokenResult,
        ]);
    }


    /**
     * Response data
     *
     * @param $status
     * @param $message
     * @param $data
     * @return array
     */
    public function responseData($status = null, $message = null, $data = null)
    {
        $response['status']    = $status;
        $response['message']   = $message;
        $response['data']      = $data;
        return $response;
    }
}
