<?php

namespace App\Exceptions;

use App\Support\ClientHelper;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Exceptions\OAuthServerException;
use Raygun4php\RaygunClient;
use League\OAuth2\Server\Exception\OAuthServerException as OAuth2Exception;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        OAuthServerException::class,
        AuthenticationException::class,
        OAuth2Exception::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param Exception $exception
     *
     * @return void
     */
    public function report(\Throwable $exception)
    {
        parent::report($exception);

        if ($this->shouldntReport($exception)) {
            return;
        }

        //only store unknown log
        if (!($exception instanceof CustomException)) {
            ClientHelper::logError($exception);
        }

        // Only send exception data on non-local environment.
        $raygunEnabled = (bool) config('services.raygun.enable', false);
        if ($raygunEnabled) {
            $raygun = resolve(RaygunClient::class);
            if (Auth::user()) {
                $auth = Auth::user();
                $user = $auth->username;
                $firstName = "";
                $fullName = $auth->name;
                $email = $auth->email;
                $isAnonymous = false;
                $uuid = $auth->id;
                $raygun->SetUser($user, $firstName, $fullName, $email, $isAnonymous, $uuid);
            }

            $tags = [config('app.env', 'unknown')];

            $raygun->SetGroupingKey(function() use ($exception) {
                // Inspect the above parameters and return a hash from the properties ...
                return $exception->getMessage(); // Naive message-based grouping only
            });

            $raygun->SendException($exception, $tags);
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request $request
     * @param Exception                 $exception
     *
     * @return Response
     */
    public function render($request, \Throwable $exception)
    {
        return parent::render($request, $exception);
    }
}
