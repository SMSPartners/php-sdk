<?php

namespace SmsPartners\Exceptions;

class ApiException extends SmsPartnersException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message, $statusCode);
    }
}
