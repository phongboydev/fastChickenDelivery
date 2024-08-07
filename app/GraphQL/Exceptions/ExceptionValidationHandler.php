<?php

namespace App\GraphQL\Exceptions;

use App\Exceptions\CustomException;
use App\Support\ClientHelper;
use App\Support\ErrorCode;
use Closure;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;
use Nuwave\Lighthouse\Execution\ErrorHandler;
use Spatie\Period\Exceptions\CannotComparePeriods;
use Spatie\Period\Exceptions\InvalidDate;
use Spatie\Period\Exceptions\InvalidPeriod;

class ExceptionValidationHandler implements ErrorHandler
{
    public static function handle(Error $error, Closure $next): array
    {
        $underlyingException = $error->getPrevious();

        //only store unknown log
        if (!($underlyingException instanceof CustomException)) {
            $user = Auth::user();
            $logError = $underlyingException ?? $error;
            ClientHelper::logError($logError, $user);
        }

        //Change default massage

        switch (true) {
            case $underlyingException instanceof InvalidPeriod:
            case $underlyingException instanceof InvalidDate:
            case $underlyingException instanceof CannotComparePeriods:
                $message = $underlyingException->getMessage() ?? __("ERR0001.internal.error");
                break;
            default:
                // message = code.category.status
                $message = __("ERR0001.internal.error");
                break;

        }
        FormattedError::setInternalErrorMessage($message);

        // Passing in the default extensions of the underlying exception
        if (!($underlyingException instanceof RendersErrorsExtensions)) {
            $error = new Error(
                $error->message,
                $error->nodes,
                $error->getSource(),
                $error->getPositions(),
                $error->getPath(),
                $underlyingException,
                self::setDefaultExtensionsContent()
            );
        }

        return $next($error);
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     *
     * @return array
     */
    public static function setDefaultExtensionsContent(): array
    {
        return [
            'reason' => 'unknown',
            'code' => ErrorCode::ERR0001,
            'status' => 'error',
            'data' => '[]',
        ];
    }
}
