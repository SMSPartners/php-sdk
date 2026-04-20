<?php

namespace SmsPartners\Exceptions;

class ValidationException extends SmsPartnersException
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
