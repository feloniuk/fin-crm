<?php

namespace App\Services\Shop;

use App\Contracts\ShopApiClientInterface;
use App\DTOs\ApiCredentialsDTO;
use App\DTOs\OrderDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HoroshopApiClient implements ShopApiClientInterface
{
    private ?string $lastError = null;
    private ?string $token = null;
    private ?Carbon $tokenExpiresAt = null;

    // Токен живёт 600 секунд, обновляем за 60 секунд до истечения
    private const TOKEN_LIFETIME_SECONDS = 600;
    private const TOKEN_REFRESH_MARGIN = 60;

    public function __construct(
        private readonly ApiCredentialsDTO $credentials,
    ) {}

    /**
     * Получить базовый URL API магазина
     */
    private function getApiUrl(string $endpoint = ''): string
    {
        $shopUrl = rtrim($this->credentials->shopUrl, '/');

        // Убеждаемся что URL начинается с https://
        if (!str_starts_with($shopUrl, 'http://') && !str_starts_with($shopUrl, 'https://')) {
            $shopUrl = 'https://' . $shopUrl;
        }

        return $shopUrl . '/api/' . ltrim($endpoint, '/');
    }

    /**
     * Авторизоваться и получить токен
     */
    public function authenticate(): bool
    {
        try {
            $response = Http::timeout(15)
                ->post($this->getApiUrl('auth/'), [
                    'login' => $this->credentials->login,
                    'password' => $this->credentials->password,
                ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'OK') {
                $this->token = $data['response']['token'] ?? null;

                if ($this->token) {
                    $this->tokenExpiresAt = now()->addSeconds(self::TOKEN_LIFETIME_SECONDS);
                    Log::info('Horoshop: Авторизация успешна', [
                        'shop_url' => $this->credentials->shopUrl,
                    ]);
                    return true;
                }
            }

            $this->lastError = 'Авторизація не вдалась: ' . ($data['response']['error'] ?? $data['status'] ?? 'Unknown error');
            Log::error('Horoshop: Ошибка авторизации', [
                'shop_url' => $this->credentials->shopUrl,
                'error' => $this->lastError,
                'response' => $data,
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->lastError = 'Помилка з\'єднання: ' . $e->getMessage();
            Log::error('Horoshop: Исключение при авторизации', [
                'shop_url' => $this->credentials->shopUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Проверить и обновить токен при необходимости
     */
    private function ensureAuthenticated(): bool
    {
        // Если токен отсутствует или истекает скоро - авторизуемся заново
        if (!$this->token || !$this->tokenExpiresAt ||
            $this->tokenExpiresAt->subSeconds(self::TOKEN_REFRESH_MARGIN)->isPast()) {
            return $this->authenticate();
        }

        return true;
    }

    /**
     * Тестирование подключения к API
     */
    public function testConnection(): bool
    {
        return $this->authenticate();
    }

    /**
     * Получить заказы начиная с определённой даты
     */
    public function getOrders(Carbon $since): Collection
    {
        $orders = collect();

        if (!$this->ensureAuthenticated()) {
            return $orders;
        }

        try {
            $offset = 0;
            $limit = 100;
            $hasMore = true;

            while ($hasMore) {
                $response = Http::timeout(30)
                    ->post($this->getApiUrl('orders/get/'), [
                        'token' => $this->token,
                        'limit' => $limit,
                        'offset' => $offset,
                        'additionalData' => true,
                    ]);

                $data = $response->json();

                if (!$response->successful() || ($data['status'] ?? '') !== 'OK') {
                    $this->lastError = 'Помилка отримання замовлень: ' . ($data['response']['error'] ?? 'Unknown error');
                    Log::error('Horoshop: Ошибка получения заказов', [
                        'shop_url' => $this->credentials->shopUrl,
                        'error' => $this->lastError,
                        'response' => $data,
                    ]);
                    break;
                }

                $ordersList = $data['response']['orders'] ?? $data['response'] ?? [];

                if (empty($ordersList) || !is_array($ordersList)) {
                    break;
                }

                foreach ($ordersList as $orderData) {
                    // Фильтруем по дате создания/обновления
                    $orderDate = null;
                    if (!empty($orderData['stat_created'])) {
                        $orderDate = Carbon::parse($orderData['stat_created']);
                    } elseif (!empty($orderData['created_at'])) {
                        $orderDate = Carbon::parse($orderData['created_at']);
                    }

                    if ($orderDate && $orderDate->gte($since)) {
                        $orders->push(OrderDTO::fromArray($orderData));
                    }
                }

                // Проверяем, есть ли ещё заказы
                if (count($ordersList) < $limit) {
                    $hasMore = false;
                } else {
                    $offset += $limit;
                }

                // Защита от бесконечного цикла
                if ($offset > 10000) {
                    Log::warning('Horoshop: Достигнут лимит пагинации', [
                        'shop_url' => $this->credentials->shopUrl,
                        'offset' => $offset,
                    ]);
                    break;
                }
            }

            Log::info('Horoshop: Получено заказов', [
                'shop_url' => $this->credentials->shopUrl,
                'count' => $orders->count(),
                'since' => $since->toDateTimeString(),
            ]);

            return $orders;
        } catch (\Throwable $e) {
            $this->lastError = 'Помилка отримання замовлень: ' . $e->getMessage();
            Log::error('Horoshop: Исключение при получении заказов', [
                'shop_url' => $this->credentials->shopUrl,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Получить заказ по внешнему ID
     */
    public function getOrderByExternalId(string $externalId): ?OrderDTO
    {
        if (!$this->ensureAuthenticated()) {
            return null;
        }

        try {
            // Horoshop API - получаем заказ по ID через общий endpoint с фильтром
            $response = Http::timeout(15)
                ->post($this->getApiUrl('orders/get/'), [
                    'token' => $this->token,
                    'order_id' => $externalId,
                    'additionalData' => true,
                ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'OK') {
                $orderData = $data['response']['orders'][0] ?? $data['response'][0] ?? $data['response'] ?? null;

                if ($orderData && is_array($orderData)) {
                    return OrderDTO::fromArray($orderData);
                }
            }

            $this->lastError = 'Замовлення не знайдено: ' . $externalId;
            return null;
        } catch (\Throwable $e) {
            $this->lastError = 'Помилка отримання замовлення: ' . $e->getMessage();
            Log::error('Horoshop: Ошибка получения заказа по ID', [
                'shop_url' => $this->credentials->shopUrl,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить текст последней ошибки
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
