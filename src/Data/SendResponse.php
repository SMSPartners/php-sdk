<?php

namespace SmsPartners\Data;

class SendResponse
{
    public readonly int $id;

    public readonly string $status;

    public readonly string $to;

    public readonly int $creditsUsed;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->status = (string) $data['status'];
        $this->to = (string) $data['to'];
        $this->creditsUsed = (int) $data['credits_used'];
    }
}
