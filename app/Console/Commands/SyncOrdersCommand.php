<?php

namespace App\Console\Commands;

use App\Actions\Order\SyncOrdersAction;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {--shop= : Синхронізувати конкретний магазин}';

    protected $description = 'Синхронізувати замовлення з магазинів';

    public function handle()
    {
        $this->info('🔄 Починаємо синхронізацію замовлень...');

        $shop = null;
        if ($this->option('shop')) {
            $shop = Shop::where('id', $this->option('shop'))->orWhere('name', $this->option('shop'))->first();

            if (!$shop) {
                $this->error('❌ Магазин не знайдено');
                return self::FAILURE;
            }

            $this->info("📦 Синхронізуємо магазин: {$shop->name}");
        } else {
            $this->info('📦 Синхронізуємо всі активні магазини');
        }

        try {
            $action = app(SyncOrdersAction::class);
            $action->execute($shop);

            $this->info('✅ Синхронізація завершена успішно');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Помилка під час синхронізації: ' . $e->getMessage());
            Log::error('Order sync failed', [
                'shop_id' => $shop?->id,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
