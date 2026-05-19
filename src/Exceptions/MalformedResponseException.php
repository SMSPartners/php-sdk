<?php

namespace SmsPartners\Exceptions;

class MalformedResponseException extends SmsPartnersException
{
    /**
     * @param  array<int|string, mixed>  $payload  The raw payload that failed to parse, for debugging.
     */
    public function __construct(
        public readonly string $missingKey,
        public readonly array $payload,
    ) {
        parent::__construct("SMS Partners API response was missing required field '{$missingKey}'.");
    }
}
