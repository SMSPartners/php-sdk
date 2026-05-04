<?php

namespace SmsPartners\Data;

use DateTimeImmutable;

class Recipient
{
    public readonly string $phone;

    public readonly string $status;

    public readonly ?DateTimeImmutable $deliveredAt;

    public readonly ?string $errorMessage;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->phone = (string) $data['phone'];
        $this->status = (string) $data['status'];
        $this->deliveredAt = isset($data['delivered_at']) ? new DateTimeImmutable($data['delivered_at']) : null;
        $this->errorMessage = isset($data['error_message']) ? (string) $data['error_message'] : null;
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
