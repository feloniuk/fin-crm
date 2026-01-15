<?php

namespace App\Filament\Widgets;

use App\Models\OurCompany;
use Filament\Widgets\Widget;

class CompanyLimitsWidget extends Widget
{
    protected static string $view = 'filament.widgets.company-limits-widget';

    protected static ?int $sort = 1;

    public function getCompanies()
    {
        return OurCompany::active()
            ->where('annual_limit', '>', 0)
            ->orderBy('name')
            ->get();
    }

    public function getCompanyLimitData(OurCompany $company)
    {
        $invoiced = $company->getYearlyInvoicedAmount();
        $limit = $company->annual_limit;
        $percent = ($invoiced / $limit) * 100;
        $remaining = $limit - $invoiced;

        $colorClass = 'success';
        if ($percent >= 90) {
            $colorClass = 'danger';
        } elseif ($percent >= 70) {
            $colorClass = 'warning';
        }

        return [
            'invoiced' => $invoiced,
            'limit' => $limit,
            'percent' => round($percent, 2),
            'remaining' => max(0, $remaining),
            'colorClass' => $colorClass,
        ];
    }
}
