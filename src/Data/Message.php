<?php

namespace SmsPartners\Data;

use DateTimeImmutable;

class Message
{
    public readonly int $id;

    public readonly string $status;

    public readonly string $body;

    public readonly ?string $from;

    public readonly ?DateTimeImmutable $scheduledAt;

    public readonly int $creditsUsed;

    public readonly DateTimeImmutable $createdAt;

    /** @var Recipient[] */
    public readonly array $recipients;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->status = (string) $data['status'];
        $this->body = (string) $data['body'];
        $this->from = isset($data['from']) ? (string) $data['from'] : null;
        $this->scheduledAt = isset($data['scheduled_at']) ? new DateTimeImmutable($data['scheduled_at']) : null;
        $this->creditsUsed = (int) $data['credits_used'];
        $this->createdAt = new DateTimeImmutable($data['created_at']);
        $this->recipients = array_map(fn ($r) => new Recipient($r), (array) ($data['recipients'] ?? []));
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
