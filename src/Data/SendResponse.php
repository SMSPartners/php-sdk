<?php

namespace SmsPartners\Data;

class SendResponse extends Message
{
    /** Convenience accessor — phone of the first recipient. */
    public readonly string $to;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->to = $this->recipients[0]->phone ?? '';
    }
}
