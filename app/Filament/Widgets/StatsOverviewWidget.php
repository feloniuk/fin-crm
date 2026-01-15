<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $newOrdersCount = Order::where('status', OrderStatus::New)->count();
        $invoicesThisMonth = Invoice::whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->count();
        $invoicesSumThisMonth = Invoice::whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->sum('total');
        $unpaidInvoicesSum = Invoice::where('is_paid', false)->sum('total');
        $unpaidInvoicesCount = Invoice::where('is_paid', false)->count();

        return [
            Stat::make('🆕 Нові замовлення', $newOrdersCount)
                ->description('без рахунків')
                ->url('/admin/orders?tableBulkAction=&tableFilters%5Bstatus%5D=new')
                ->color($newOrdersCount > 0 ? 'warning' : 'success'),

            Stat::make('📄 Рахунків цього місяця', $invoicesThisMonth)
                ->description('на суму ' . number_format($invoicesSumThisMonth, 2, ',', ' ') . ' грн')
                ->url('/admin/invoices')
                ->color('info'),

            Stat::make('⏳ Неоплачених рахунків', $unpaidInvoicesCount)
                ->description('на суму ' . number_format($unpaidInvoicesSum, 2, ',', ' ') . ' грн')
                ->url('/admin/invoices?tableFilters%5Bis_paid%5D=false')
                ->color($unpaidInvoicesCount > 0 ? 'danger' : 'success'),
        ];
    }
}
