<?php

namespace SmsPartners\Data;

use DateTimeImmutable;
use SmsPartners\Exceptions\MalformedResponseException;

class Recipient
{
    public readonly string $phone;

    public readonly string $status;

    public readonly ?DateTimeImmutable $deliveredAt;

    public readonly ?string $errorMessage;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public function __construct(array $data)
    {
        $this->phone = Payload::requireString($data, 'phone');
        $this->status = Payload::optionalString($data, 'status') ?? 'queued';
        $this->deliveredAt = Payload::optionalDateTime($data, 'delivered_at');
        $this->errorMessage = Payload::optionalString($data, 'error_message');
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
