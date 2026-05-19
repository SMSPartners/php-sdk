<?php

namespace SmsPartners\Data;

use DateTimeImmutable;
use SmsPartners\Exceptions\MalformedResponseException;

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
     * @param  array<string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public function __construct(array $data)
    {
        $this->id = Payload::requireInt($data, 'id');
        $this->status = Payload::requireString($data, 'status');
        $this->body = Payload::optionalString($data, 'body') ?? '';
        $this->from = Payload::optionalString($data, 'from');
        $this->scheduledAt = Payload::optionalDateTime($data, 'scheduled_at');
        $this->creditsUsed = Payload::optionalInt($data, 'credits_used');
        $this->createdAt = Payload::requireDateTime($data, 'created_at');
        $this->recipients = array_map(
            fn ($r) => new Recipient((array) $r),
            Payload::optionalArray($data, 'recipients'),
        );
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
