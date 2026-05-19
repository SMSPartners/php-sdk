<?php

namespace SmsPartners\Data;

class SendResponse extends Message
{
    /** Convenience accessor — phone of the first recipient. */
    public readonly string $to;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        // The API does not return a flat `to`; derive it from the first
        // recipient. Fall back to an explicit `to` key for forward
        // compatibility with hypothetical envelope changes.
        $this->to = $this->recipients[0]->phone
            ?? Payload::optionalString($data, 'to')
            ?? '';
    }
}
