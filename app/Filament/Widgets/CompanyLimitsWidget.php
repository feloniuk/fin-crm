<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\OurCompany;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class CompanyLimitsWidget extends Widget
{
    protected static string $view = 'filament.widgets.company-limits-widget';

    protected static ?int $sort = 1;

    // Disable polling
    protected static ?string $pollingInterval = null;

    public function getCompaniesWithLimits(): array
    {
        return Cache::remember('company_limits', 60, function () {
            $companies = OurCompany::active()
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'annual_limit', 'external_sales_amount', 'remaining_limit_override']);

            if ($companies->isEmpty()) {
                return [];
            }

            // Filter companies with limits (global or individual)
            $companies = $companies->filter(function ($company) {
                return $company->hasLimit();
            });

            if ($companies->isEmpty()) {
                return [];
            }

            // Get yearly PAID invoice amounts for all companies in one query (ИЗМЕНЕНО)
            $year = now()->year;
            $paidAmounts = Invoice::whereYear('invoice_date', $year)
                ->where('is_paid', true)  // ИЗМЕНЕНО: только оплаченные
                ->whereIn('our_company_id', $companies->pluck('id'))
                ->groupBy('our_company_id')
                ->selectRaw('our_company_id, COALESCE(SUM(total), 0) as total_paid')
                ->pluck('total_paid', 'our_company_id');

            // Build result array
            $result = [];
            foreach ($companies as $company) {
                $effectiveLimit = $company->getEffectiveLimit();

                if (!$effectiveLimit) {
                    continue;
                }

                $paidInSystem = (float) ($paidAmounts[$company->id] ?? 0);
                $externalSales = (float) $company->external_sales_amount;

                // Расчет по формуле
                if ($company->remaining_limit_override !== null) {
                    $remaining = (float) $company->remaining_limit_override;
                    $totalUsed = $effectiveLimit - $remaining;
                } else {
                    $totalUsed = $paidInSystem + $externalSales;
                    $remaining = $effectiveLimit - $totalUsed;
                }

                $percent = $effectiveLimit > 0 ? ($totalUsed / $effectiveLimit) * 100 : 0;

                $colorClass = match (true) {
                    $percent >= 100 => 'danger',
                    $percent >= 90 => 'warning',
                    $percent >= 70 => 'info',
                    default => 'success',
                };

                $result[] = [
                    'name' => $company->name,
                    'type' => $company->type->getLabel(),
                    'paid_in_system' => $paidInSystem,
                    'external_sales' => $externalSales,
                    'total_used' => $totalUsed,
                    'limit' => $effectiveLimit,
                    'percent' => round($percent, 2),
                    'remaining' => $remaining,
                    'colorClass' => $colorClass,
                    'has_override' => $company->remaining_limit_override !== null,
                ];
            }

            return $result;
        });
    }
}
