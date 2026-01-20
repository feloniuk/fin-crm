<?php

namespace App\DTOs;

class ApiCredentialsDTO
{
    public function __construct(
        // Horoshop credentials
        public readonly string $login = '',
        public readonly string $password = '',
        public readonly string $shopUrl = '',
        // Prom.ua credentials
        public readonly string $apiToken = '',
        // Additional data
        public readonly array $additionalData = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            login: $data['login'] ?? '',
            password: $data['password'] ?? '',
            shopUrl: $data['shop_url'] ?? '',
            apiToken: $data['api_token'] ?? $data['api_key'] ?? '',
            additionalData: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'login' => $this->login,
            'password' => $this->password,
            'shop_url' => $this->shopUrl,
            'api_token' => $this->apiToken,
        ];
    }
}
