<?php

namespace SmsPartners\Data;

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
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->email = (string) $data['email'];
        $this->balanceCredits = (int) $data['balance_credits'];
        $this->status = (string) $data['status'];
        $this->autoTopupEnabled = (bool) $data['auto_topup_enabled'];
        $this->autoTopupThreshold = (int) $data['auto_topup_threshold'];
        $this->autoTopupAmount = (int) $data['auto_topup_amount'];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
