<?php

namespace SmsPartners\Exceptions;

use Throwable;

class ValidationException extends SmsPartnersException
{
    /**
     * @param  array<string, string[]>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
