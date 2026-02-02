<?php

namespace App\Console\Commands;

use App\Actions\Order\SyncOrdersAction;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {--shop= : Синхронізувати конкретний магазин} {--force : Переінціалізувати синхронізацію (загрузити всі заказы за 30 днів)} {--all : Синхронізувати всі активні магазини незалежно від інтервалу}';

    protected $description = 'Синхронізувати замовлення з магазинів';

    public function handle()
    {
        $this->info('🔄 Починаємо синхронізацію замовлень...');

        $action = app(SyncOrdersAction::class);

        // Sync specific shop
        if ($this->option('shop')) {
            $shop = Shop::where('id', $this->option('shop'))->orWhere('name', $this->option('shop'))->first();

            if (!$shop) {
                $this->error('❌ Магазин не знайдено');
                return self::FAILURE;
            }

            $this->info("📦 Синхронізуємо магазин: {$shop->name}");

            if ($this->option('force')) {
                $this->info('⚠️  Режим --force: переінціалізація синхронізації');
                $shop->update(['last_synced_at' => null]);
                $this->info("✓ Скидаємо last_synced_at для магазину: {$shop->name}");
            }

            try {
                $action->execute($shop);
                $this->info('✅ Синхронізація завершена успішно');
                return self::SUCCESS;
            } catch (\Exception $e) {
                $this->error('❌ Помилка під час синхронізації: ' . $e->getMessage());
                Log::error('Order sync failed', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
                return self::FAILURE;
            }
        }

        // Sync multiple shops based on their intervals
        if ($this->option('force')) {
            $this->info('⚠️  Режим --force: переінціалізація синхронізації');
            Shop::where('is_active', true)->update(['last_synced_at' => null]);
            $this->info('✓ Скидаємо last_synced_at для всіх активних магазинів');
        }

        // Get shops to sync
        $shops = Shop::active()
            ->when(!$this->option('all') && !$this->option('force'), fn($q) => $q->dueForSync())
            ->get();

        if ($shops->isEmpty()) {
            $this->info('ℹ️  Немає магазинів для синхронізації');
            return self::SUCCESS;
        }

        $this->info("📦 Знайдено магазинів для синхронізації: {$shops->count()}");

        $hasErrors = false;
        foreach ($shops as $shop) {
            $this->info("  → Синхронізуємо: {$shop->name}");

            try {
                $action->execute($shop);
                $this->info("    ✓ {$shop->name} - успішно");
            } catch (\Exception $e) {
                $hasErrors = true;
                $this->error("    ✗ {$shop->name} - помилка: " . $e->getMessage());
                Log::error('Order sync failed', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($hasErrors) {
            $this->warn('⚠️  Синхронізація завершена з помилками');
            return self::FAILURE;
        }

        $this->info('✅ Синхронізація завершена успішно');
        return self::SUCCESS;
    }
}
