<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class OrderDTO
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $customerName,
        public readonly string $customerPhone,
        public readonly string $customerComment,
        public readonly float $totalAmount,
        public readonly Collection $items,
        public readonly array $rawData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            externalId: (string) $data['external_id'] ?? (string) $data['id'] ?? '',
            customerName: $data['customer_name'] ?? $data['customer']['name'] ?? '',
            customerPhone: $data['customer_phone'] ?? $data['customer']['phone'] ?? '',
            customerComment: $data['customer_comment'] ?? $data['comment'] ?? '',
            totalAmount: (float) ($data['total_amount'] ?? $data['total'] ?? 0),
            items: collect($data['items'] ?? $data['products'] ?? []),
            rawData: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_comment' => $this->customerComment,
            'total_amount' => $this->totalAmount,
            'items' => $this->items->toArray(),
            'raw_data' => $this->rawData,
        ];
    }
}
