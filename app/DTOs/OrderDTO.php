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
        public readonly string $deliveryName,
        public readonly string $deliveryAddress,
        public readonly string $deliveryCity,
        public readonly string $deliveryType,
        public readonly string $paymentType,
        public readonly bool $payed,
        public readonly string $managerComment,
        public readonly string $currency,
        public readonly int $apiStatus,
        public readonly float $totalAmount,
        public readonly Collection $items,
        public readonly array $rawData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        // Horoshop формат: order_id, delivery_name, delivery_phone, delivery_address, total_sum, products
        // Prom.ua формат: id, client_first_name+client_last_name, phone, comment, price, products

        $externalId = (string) ($data['order_id'] ?? $data['external_id'] ?? $data['id'] ?? '');

        // Имя клиента - сначала delivery_name (Horoshop), потом client_fio, потом Prom.ua
        $customerName = $data['delivery_name']
            ?? $data['customer_name']
            ?? $data['client_fio']
            ?? $data['client_name']
            ?? ($data['customer']['name'] ?? null)
            ?? trim(($data['client_first_name'] ?? '') . ' ' . ($data['client_last_name'] ?? ''))
            ?? '';

        // Телефон клиента - сначала delivery_phone (Horoshop), потом других вариантов
        $customerPhone = $data['delivery_phone']
            ?? $data['customer_phone']
            ?? $data['client_phone']
            ?? $data['phone']
            ?? ($data['customer']['phone'] ?? '')
            ?? '';

        // Комментарий - client comment, не manager comment
        $customerComment = $data['comment']
            ?? $data['customer_comment']
            ?? $data['client_comment']
            ?? '';

        // Delivery данные (Horoshop специфичные)
        $deliveryName = $data['delivery_name'] ?? '';
        $deliveryAddress = $data['delivery_address'] ?? '';
        $deliveryCity = $data['delivery_city'] ?? '';
        $deliveryType = is_array($data['delivery_type'] ?? null) ? ($data['delivery_type']['title'] ?? '') : ($data['delivery_type'] ?? '');

        // Payment данные
        $paymentType = is_array($data['payment_type'] ?? null) ? ($data['payment_type']['title'] ?? '') : ($data['payment_type'] ?? '');
        $payed = (bool) ($data['payed'] ?? false);

        // Manager comment (Horoshop специфичное)
        $managerComment = $data['manager_comment'] ?? '';

        // Currency
        $currency = $data['currency'] ?? 'UAH';

        // API Status (Horoshop stat_status)
        $apiStatus = (int) ($data['stat_status'] ?? 0);

        // Сумма заказа (Horoshop: total_sum, Prom: price)
        $totalAmount = (float) ($data['total_amount'] ?? $data['total_sum'] ?? $data['total'] ?? $data['price'] ?? 0);

        // Товары (Horoshop: products, Prom: products)
        $items = collect($data['items'] ?? $data['products'] ?? []);

        return new self(
            externalId: $externalId,
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerComment: $customerComment,
            deliveryName: $deliveryName,
            deliveryAddress: $deliveryAddress,
            deliveryCity: $deliveryCity,
            deliveryType: $deliveryType,
            paymentType: $paymentType,
            payed: $payed,
            managerComment: $managerComment,
            currency: $currency,
            apiStatus: $apiStatus,
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
            'delivery_name' => $this->deliveryName,
            'delivery_address' => $this->deliveryAddress,
            'delivery_city' => $this->deliveryCity,
            'delivery_type' => $this->deliveryType,
            'payment_type' => $this->paymentType,
            'payed' => $this->payed,
            'manager_comment' => $this->managerComment,
            'currency' => $this->currency,
            'api_status' => $this->apiStatus,
            'total_amount' => $this->totalAmount,
            'items' => $this->items->toArray(),
            'raw_data' => $this->rawData,
        ];
    }
}
