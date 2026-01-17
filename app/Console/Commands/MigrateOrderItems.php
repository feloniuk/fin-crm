<?php

namespace App\Console\Commands;

use App\Actions\Order\SyncOrderItemsAction;
use App\Models\Order;
use Illuminate\Console\Command;

class MigrateOrderItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:migrate-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing orders to use order_items from raw_data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Починаємо міграцію замовлень...');

        $orders = Order::whereDoesntHave('items')->get();

        if ($orders->isEmpty()) {
            $this->info('✅ Немає замовлень для міграції');
            return self::SUCCESS;
        }

        $this->info("📦 Обробляємо {$orders->count()} замовлень");

        $syncOrderItems = new SyncOrderItemsAction();
        $migratedCount = 0;

        foreach ($orders as $order) {
            try {
                $syncOrderItems->execute($order);
                $this->line("✓ Замовлення #{$order->external_id} оброблено");
                $migratedCount++;
            } catch (\Exception $e) {
                $this->error("✗ Помилка для замовлення #{$order->external_id}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Міграція завершена: {$migratedCount} замовлень оброблено");

        return self::SUCCESS;
    }
}
