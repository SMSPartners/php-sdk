<?php

namespace SmsPartners\Data;

use DateTimeImmutable;
use SmsPartners\Exceptions\MalformedResponseException;

class WebhookEvent
{
    public readonly string $event;

    public readonly DateTimeImmutable $timestamp;

    /** @var array<string, mixed> */
    public readonly array $data;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MalformedResponseException
     */
    public function __construct(array $payload)
    {
        $this->event = Payload::requireString($payload, 'event');
        $this->timestamp = Payload::requireDateTime($payload, 'timestamp');
        $this->data = Payload::optionalArray($payload, 'data');
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
