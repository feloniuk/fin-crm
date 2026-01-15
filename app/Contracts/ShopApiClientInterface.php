<?php

namespace App\Contracts;

use App\DTOs\ApiCredentialsDTO;
use App\DTOs\OrderDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface ShopApiClientInterface
{
    /**
     * Test the API connection
     */
    public function testConnection(): bool;

    /**
     * Authenticate with the shop API
     */
    public function authenticate(): bool;

    /**
     * Get orders from the shop API since a specific date
     */
    public function getOrders(Carbon $since): Collection;

    /**
     * Get a single order by external ID
     */
    public function getOrderByExternalId(string $externalId): ?OrderDTO;

    /**
     * Get the last error message
     */
    public function getLastError(): ?string;
}
