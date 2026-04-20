<?php

namespace SmsPartners\Data;

use DateTimeImmutable;

class WebhookEvent
{
    public readonly string $event;

    public readonly DateTimeImmutable $timestamp;

    /** @var array<string, mixed> */
    public readonly array $data;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload)
    {
        $this->event = (string) $payload['event'];
        $this->timestamp = new DateTimeImmutable($payload['timestamp']);
        $this->data = (array) ($payload['data'] ?? []);
    }

    public function isDelivered(): bool
    {
        return $this->event === 'message.delivered';
    }

    public function isFailed(): bool
    {
        return $this->event === 'message.failed';
    }

    public function messageId(): ?int
    {
        return isset($this->data['message_id']) ? (int) $this->data['message_id'] : null;
    }

    public function recipientPhone(): ?string
    {
        return $this->data['recipient']['phone'] ?? null;
    }
}
