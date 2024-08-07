<?php

namespace App\Exceptions;

use Exception;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;

class HumanErrorException extends CustomException
{

    /**
     * @var @string
     */
    private $reason;
    private $errorCode;
    private $category;
    private $status;
    private $data;

    /**
     * CustomException constructor.
     *
     * @param  string  $message
     * @param  string  $reason
     * @return void
     */
    public function __construct(string $reason, string $errorCode = "", string $message = "", array $data = [], $status = "error", string $category = 'custom_exception')
    {
        if (!$message) {
            $message = $reason;
        }
        parent::__construct($message, $reason, $errorCode, $data, $status, $category);
    }

}
