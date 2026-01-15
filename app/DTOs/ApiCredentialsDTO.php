<?php

namespace App\DTOs;

class ApiCredentialsDTO
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiSecret = '',
        public readonly string $shopUrl = '',
        public readonly array $additionalData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            apiKey: $data['api_key'] ?? '',
            apiSecret: $data['api_secret'] ?? '',
            shopUrl: $data['shop_url'] ?? '',
            additionalData: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'shop_url' => $this->shopUrl,
            ...$this->additionalData,
        ];
    }
}
