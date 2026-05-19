<?php

namespace SmsPartners\Data;

use SmsPartners\Exceptions\MalformedResponseException;

class AccountResponse
{
    public readonly int $id;

    public readonly string $name;

    public readonly string $email;

    public readonly int $balanceCredits;

    public readonly string $status;

    public readonly bool $autoTopupEnabled;

    public readonly int $autoTopupThreshold;

    public readonly int $autoTopupAmount;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public function __construct(array $data)
    {
        $this->id = Payload::requireInt($data, 'id');
        $this->name = Payload::optionalString($data, 'name') ?? '';
        $this->email = Payload::optionalString($data, 'email') ?? '';
        $this->balanceCredits = Payload::optionalInt($data, 'balance_credits');
        $this->status = Payload::optionalString($data, 'status') ?? 'active';
        $this->autoTopupEnabled = Payload::optionalBool($data, 'auto_topup_enabled');
        $this->autoTopupThreshold = Payload::optionalInt($data, 'auto_topup_threshold');
        $this->autoTopupAmount = Payload::optionalInt($data, 'auto_topup_amount');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
