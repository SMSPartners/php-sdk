<?php

namespace SmsPartners\Exceptions;

use Throwable;

class InsufficientCreditsException extends SmsPartnersException
{
    public function __construct(
        string $message,
        public readonly int $balance,
        public readonly int $required,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
