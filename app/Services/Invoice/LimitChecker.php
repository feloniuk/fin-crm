<?php

namespace App\Services\Invoice;

use App\Models\OurCompany;

class LimitChecker
{
    public function checkLimit(OurCompany $company, float $invoiceTotal): array
    {
        if (!$company->hasLimit()) {
            return [
                'isExceeded' => false,
                'isWarning' => false,
                'remaining' => null,
                'percent' => null,
            ];
        }

        $yearlyTotal = $company->getYearlyInvoicedAmount();
        $newTotal = $yearlyTotal + $invoiceTotal;
        $limit = $company->annual_limit;
        $remaining = $limit - $newTotal;
        $percent = ($newTotal / $limit) * 100;

        return [
            'isExceeded' => $newTotal > $limit,
            'isWarning' => $percent >= 90,
            'remaining' => max(0, $remaining),
            'percent' => round($percent, 2),
            'newTotal' => $newTotal,
            'limit' => $limit,
        ];
    }

    public function hasLimit(OurCompany $company): bool
    {
        return $company->hasLimit();
    }

    public function getRemainingLimit(OurCompany $company): ?float
    {
        if (!$this->hasLimit($company)) {
            return null;
        }

        return max(0, $company->annual_limit - $company->getYearlyInvoicedAmount());
    }

    public function getLimitUsagePercent(OurCompany $company): ?float
    {
        if (!$this->hasLimit($company)) {
            return null;
        }

        return round(
            ($company->getYearlyInvoicedAmount() / $company->annual_limit) * 100,
            2
        );
    }
}
