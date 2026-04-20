<?php

namespace SmsPartners\Exceptions;

class InsufficientCreditsException extends SmsPartnersException
{
    public function __construct(
        string $message,
        public readonly int $balance,
        public readonly int $required,
    ) {
        parent::__construct($message);
    }
}
