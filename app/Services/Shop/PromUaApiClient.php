<?php

namespace App\Services\Shop;

use App\Contracts\ShopApiClientInterface;
use App\DTOs\ApiCredentialsDTO;
use App\DTOs\OrderDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PromUaApiClient implements ShopApiClientInterface
{
    private ?string $lastError = null;

    public function __construct(
        private readonly ApiCredentialsDTO $credentials,
    ) {}

    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->credentials->apiKey,
            ])->timeout(10)->get('https://my.prom.ua/api/v1/me');

            if ($response->successful()) {
                return true;
            }

            $this->lastError = 'API returned status: ' . $response->status();
            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function authenticate(): bool
    {
        return $this->testConnection();
    }

    public function getOrders(Carbon $since): Collection
    {
        try {
            $orders = collect();
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->credentials->apiKey,
                ])->timeout(30)->get('https://my.prom.ua/api/v1/orders/list', [
                    'updated_from' => $since->toIso8601String(),
                    'limit' => 100,
                    'page' => $page,
                ]);

                if ($response->successful()) {
                    $data = $response->json('data', []);
                    if (empty($data)) {
                        $hasMore = false;
                    } else {
                        foreach ($data as $orderData) {
                            $orders->push(OrderDTO::fromArray($orderData));
                        }
                        $page++;
                    }
                } else {
                    $this->lastError = 'Failed to fetch orders: ' . $response->status();
                    $hasMore = false;
                }
            }

            return $orders;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return collect();
        }
    }

    public function getOrderByExternalId(string $externalId): ?OrderDTO
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->credentials->apiKey,
            ])->timeout(10)->get("https://my.prom.ua/api/v1/orders/{$externalId}");

            if ($response->successful()) {
                return OrderDTO::fromArray($response->json('data', []));
            }

            $this->lastError = 'Order not found: ' . $response->status();
            return null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
