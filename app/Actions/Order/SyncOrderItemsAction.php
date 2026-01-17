<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductExternalId;

class SyncOrderItemsAction
{
    /**
     * Синхронізує товари замовлення з raw_data
     */
    public function execute(Order $order): void
    {
        // 1. Визначити source з shop type
        $source = $order->shop->type->value; // 'horoshop' або 'prom_ua'

        // 2. Отримати товари з raw_data
        $items = $order->getItemsFromRawData();

        if (empty($items)) {
            // Видалити всі order_items, якщо товарів немає
            $order->items()->delete();
            return;
        }

        // 3. Синхронізувати товари
        $itemIds = [];
        foreach ($items as $item) {
            $product = $this->findOrCreateProduct($item, $source);

            // Створити або оновити OrderItem
            $orderItem = OrderItem::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'name' => $item['name'],
                ],
                [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_type' => null,
                    'discount_value' => null,
                ]
            );

            $itemIds[] = $orderItem->id;
        }

        // 4. Видалити товари, яких більше немає в raw_data
        $order->items()->whereNotIn('id', $itemIds)->delete();

        // 5. Перерахувати totals замовлення
        $order->recalculateTotals();
    }

    /**
     * Знайти або створити Product
     */
    private function findOrCreateProduct(array $item, string $source): Product
    {
        // 1. Спочатку шукаємо за external_id (якщо є)
        if (!empty($item['external_id'])) {
            $product = Product::findByExternalId($item['external_id'], $source);
            if ($product) {
                return $product;
            }
        }

        // 2. Якщо не знайшли - шукаємо або створюємо за name
        $product = Product::findOrCreateByName($item['name']);

        // 3. Зберігаємо зв'язок external_id → product (якщо є external_id)
        if (!empty($item['external_id'])) {
            ProductExternalId::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'source' => $source,
                ],
                ['external_id' => $item['external_id']]
            );
        }

        return $product;
    }
}
