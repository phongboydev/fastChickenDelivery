<?php

namespace App\Exceptions;

use App\Support\ErrorCode;
use Exception;
use Nuwave\Lighthouse\Exceptions\RendersErrorsExtensions;
use GraphQL\Error\ClientAware;


class CustomException extends Exception implements ClientAware, RendersErrorsExtensions
{
    /**
     * @var @string
     */
    private $reason;
    private $errorCode;
    private $category;
    private $status;
    private $data;
    private $storage;
    private $clientId;

    /**
     * CustomException constructor.
     *
     * @param  string  $message
     * @param  string  $reason
     * @return void
     */
    public function __construct(
        string $message,
        string $reason,
        string $errorCode = null,
        array $data = [],
        string $status = "error",
        string $category = 'custom_exception'
    ) {
        parent::__construct($message);
        $this->category = $category;
        $this->reason = $reason;
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->data = $data;
    }

    /**
     * Returns true when exception message is safe to be displayed to a client.
     *
     * @api
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * Returns string describing a category of the error.
     *
     * Value "graphql" is reserved for errors produced by query parsing or validation, do not use it.
     *
     * @api
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Return the content that is put in the "extensions" part
     * of the returned error.
     *
     * @return array
     */
    public function extensionsContent(): array
    {
        return [
            'reason' => $this->reason,
            'code' => $this->errorCode ?? ErrorCode::ERR0001,
            'data' => json_encode($this->data),
            'status' => $this->status,
            'category' => $this->category,
        ];
    }
}
