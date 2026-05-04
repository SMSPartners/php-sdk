<?php

namespace SmsPartners\Data;

class MessagePage
{
    /** @var Message[] */
    public readonly array $data;

    public readonly int $total;

    public readonly int $perPage;

    public readonly int $currentPage;

    public readonly int $lastPage;

    /**
     * @param array<string, mixed> $response
     */
    public function __construct(array $response)
    {
        $this->data = array_map(fn ($m) => new Message($m), (array) ($response['data'] ?? []));
        $meta = (array) ($response['meta'] ?? []);
        $this->total = (int) ($meta['total'] ?? 0);
        $this->perPage = (int) ($meta['per_page'] ?? 25);
        $this->currentPage = (int) ($meta['current_page'] ?? 1);
        $this->lastPage = (int) ($meta['last_page'] ?? 1);
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
}
