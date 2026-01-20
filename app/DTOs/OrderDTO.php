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
        // Horoshop формат: order_id, client_fio, client_phone, comment, total_sum, products
        // Prom.ua формат: id, client_first_name+client_last_name, phone, comment, price, products

        $externalId = (string) ($data['order_id'] ?? $data['external_id'] ?? $data['id'] ?? '');

        // Имя клиента
        $customerName = $data['customer_name']
            ?? $data['client_fio']
            ?? $data['client_name']
            ?? ($data['customer']['name'] ?? null)
            ?? trim(($data['client_first_name'] ?? '') . ' ' . ($data['client_last_name'] ?? ''))
            ?? '';

        // Телефон клиента
        $customerPhone = $data['customer_phone']
            ?? $data['client_phone']
            ?? $data['phone']
            ?? ($data['customer']['phone'] ?? '')
            ?? '';

        // Комментарий
        $customerComment = $data['customer_comment']
            ?? $data['comment']
            ?? $data['client_comment']
            ?? '';

        // Сумма заказа (Horoshop: total_sum, Prom: price)
        $totalAmount = (float) ($data['total_amount'] ?? $data['total_sum'] ?? $data['total'] ?? $data['price'] ?? 0);

        // Товары (Horoshop: products, Prom: products)
        $items = collect($data['items'] ?? $data['products'] ?? []);

        return new self(
            externalId: $externalId,
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerComment: $customerComment,
            totalAmount: $totalAmount,
            items: $items,
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
