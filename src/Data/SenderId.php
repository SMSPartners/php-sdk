<?php

namespace SmsPartners\Data;

class SenderId
{
    public readonly int $id;

    public readonly string $name;

    public readonly string $status;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->name = (string) $data['name'];
        $this->status = (string) $data['status'];
    }
}
