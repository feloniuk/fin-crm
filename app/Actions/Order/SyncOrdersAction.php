<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Events\OrderSynced;
use App\Events\SyncFailed;
use App\Models\Order;
use App\Models\Shop;
use App\Services\Shop\ShopApiClientFactory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SyncOrdersAction
{
    public function execute(?Shop $shop = null): void
    {
        $shops = $shop ? collect([$shop]) : Shop::where('is_active', true)->get();

        foreach ($shops as $currentShop) {
            $this->syncShop($currentShop);
        }
    }

    private function syncShop(Shop $shop): void
    {
        try {
            $client = ShopApiClientFactory::make($shop);

            // Test connection first
            if (!$client->testConnection()) {
                throw new \Exception('Failed to connect to API: ' . $client->getLastError());
            }

            // Get last sync time or 7 days ago
            $sinceDate = $shop->last_synced_at ?? now()->subDays(7);

            // Fetch orders from API
            $apiOrders = $client->getOrders($sinceDate);

            if ($apiOrders->isEmpty()) {
                $shop->update(['last_synced_at' => now()]);
                OrderSynced::dispatch($shop, 0, 0, 0);
                return;
            }

            $newOrdersCount = 0;
            $updatedOrdersCount = 0;

            $syncOrderItems = new SyncOrderItemsAction();

            // Process each order from API
            foreach ($apiOrders as $orderDTO) {
                $existingOrder = Order::where('shop_id', $shop->id)
                    ->where('external_id', $orderDTO->externalId)
                    ->first();

                if ($existingOrder) {
                    // Update existing order
                    $existingOrder->update([
                        'customer_name' => $orderDTO->customerName,
                        'customer_phone' => $orderDTO->customerPhone,
                        'customer_comment' => $orderDTO->customerComment,
                        'total_amount' => $orderDTO->totalAmount,
                        'raw_data' => $orderDTO->rawData,
                        'synced_at' => now(),
                    ]);
                    $updatedOrdersCount++;
                } else {
                    // Create new order
                    $existingOrder = Order::create([
                        'shop_id' => $shop->id,
                        'external_id' => $orderDTO->externalId,
                        'customer_name' => $orderDTO->customerName,
                        'customer_phone' => $orderDTO->customerPhone,
                        'customer_comment' => $orderDTO->customerComment,
                        'total_amount' => $orderDTO->totalAmount,
                        'status' => OrderStatus::NEW,
                        'raw_data' => $orderDTO->rawData,
                        'synced_at' => now(),
                    ]);
                    $newOrdersCount++;
                }

                // Синхронізувати items замовлення
                $syncOrderItems->execute($existingOrder);
            }

            // Update shop's last sync time
            $shop->update(['last_synced_at' => now()]);

            // Dispatch success event
            OrderSynced::dispatch(
                $shop,
                $apiOrders->count(),
                $newOrdersCount,
                $updatedOrdersCount
            );
        } catch (\Throwable $e) {
            // Dispatch failure event
            SyncFailed::dispatch($shop, $e->getMessage());
        }
    }
}
