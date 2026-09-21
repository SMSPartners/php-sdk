<?php

namespace SmsPartners\Exceptions;

use Throwable;

class ApiException extends SmsPartnersException
{
    public function __construct(string $message, public readonly int $statusCode, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }
}
