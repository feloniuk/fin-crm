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
            // Get all companies with limits in one query
            $companies = OurCompany::active()
                ->where('annual_limit', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'annual_limit']);

            if ($companies->isEmpty()) {
                return [];
            }

            // Get yearly invoiced amounts for all companies in one query
            $year = now()->year;
            $invoicedAmounts = Invoice::whereYear('invoice_date', $year)
                ->whereIn('our_company_id', $companies->pluck('id'))
                ->groupBy('our_company_id')
                ->selectRaw('our_company_id, COALESCE(SUM(total), 0) as total_invoiced')
                ->pluck('total_invoiced', 'our_company_id');

            // Build result array
            $result = [];
            foreach ($companies as $company) {
                $invoiced = (float) ($invoicedAmounts[$company->id] ?? 0);
                $limit = (float) $company->annual_limit;
                $percent = $limit > 0 ? ($invoiced / $limit) * 100 : 0;
                $remaining = $limit - $invoiced;

                $colorClass = 'success';
                if ($percent >= 90) {
                    $colorClass = 'danger';
                } elseif ($percent >= 70) {
                    $colorClass = 'warning';
                }

                $result[] = [
                    'name' => $company->name,
                    'invoiced' => $invoiced,
                    'limit' => $limit,
                    'percent' => round($percent, 2),
                    'remaining' => max(0, $remaining),
                    'colorClass' => $colorClass,
                ];
            }

            return $result;
        });
    }
}
