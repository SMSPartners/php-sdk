<?php

namespace SmsPartners\Data;

use SmsPartners\Exceptions\MalformedResponseException;

class SenderId
{
    public readonly int $id;

    public readonly string $name;

    public readonly string $status;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public function __construct(array $data)
    {
        $this->id = Payload::requireInt($data, 'id');
        $this->name = Payload::requireString($data, 'name');
        $this->status = Payload::optionalString($data, 'status') ?? 'pending';
    }
}
