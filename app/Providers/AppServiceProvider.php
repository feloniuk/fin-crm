<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Shop;
use App\Policies\ShopPolicy;
use App\Models\OurCompany;
use App\Policies\OurCompanyPolicy;
use App\Models\Counterparty;
use App\Policies\CounterpartyPolicy;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Observers\InvoiceObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind services for dependency injection
        $this->app->singleton(\App\Services\Invoice\InvoiceCalculator::class);
        $this->app->singleton(\App\Services\Invoice\LimitChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        Invoice::observe(InvoiceObserver::class);

        // Register policies
        Gate::policy(Shop::class, ShopPolicy::class);
        Gate::policy(OurCompany::class, OurCompanyPolicy::class);
        Gate::policy(Counterparty::class, CounterpartyPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Schedule commands
        $this->scheduleCommands();
    }

    private function scheduleCommands(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Sync orders every 15 minutes
            $schedule->command('orders:sync')
                ->everyFifteenMinutes()
                ->withoutOverlapping()
                ->name('sync-orders')
                ->description('Синхронізувати замовлення з магазинів');

            // Check limits daily at 09:00
            $schedule->command('limits:check')
                ->dailyAt('09:00')
                ->withoutOverlapping()
                ->name('check-limits')
                ->description('Перевірити ліміти ФОП компаній');
        });
    }
}
