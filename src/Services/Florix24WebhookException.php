<?php
namespace App\Services;

use RuntimeException;

class Florix24WebhookException extends RuntimeException
{
    public int $httpStatus;
    public string $errorCode;

    public function __construct(string $errorCode, string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }
}
