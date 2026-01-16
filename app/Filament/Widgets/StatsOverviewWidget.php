<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    // Disable polling to reduce load
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // Cache stats for 1 minute to reduce DB load
        $stats = Cache::remember('dashboard_stats', 60, function () {
            // Single query for orders count
            $newOrdersCount = Order::where('status', OrderStatus::NEW)->count();

            // Single query for invoices this month (count + sum)
            $invoicesThisMonth = Invoice::whereMonth('invoice_date', now()->month)
                ->whereYear('invoice_date', now()->year)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as sum')
                ->first();

            // Single query for unpaid invoices (count + sum)
            $unpaidInvoices = Invoice::where('is_paid', false)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as sum')
                ->first();

            return [
                'newOrdersCount' => $newOrdersCount,
                'invoicesCount' => $invoicesThisMonth->count ?? 0,
                'invoicesSum' => $invoicesThisMonth->sum ?? 0,
                'unpaidCount' => $unpaidInvoices->count ?? 0,
                'unpaidSum' => $unpaidInvoices->sum ?? 0,
            ];
        });

        return [
            Stat::make('Нові замовлення', $stats['newOrdersCount'])
                ->description('без рахунків')
                ->url('/admin/orders?tableFilters%5Bstatus%5D%5Bvalue%5D=new')
                ->color($stats['newOrdersCount'] > 0 ? 'warning' : 'success'),

            Stat::make('Рахунків цього місяця', $stats['invoicesCount'])
                ->description('на суму ' . number_format($stats['invoicesSum'], 2, ',', ' ') . ' грн')
                ->url('/admin/invoices')
                ->color('info'),

            Stat::make('Неоплачених рахунків', $stats['unpaidCount'])
                ->description('на суму ' . number_format($stats['unpaidSum'], 2, ',', ' ') . ' грн')
                ->url('/admin/invoices?tableFilters%5Bis_paid%5D%5Bvalue%5D=0')
                ->color($stats['unpaidCount'] > 0 ? 'danger' : 'success'),
        ];
    }
}
