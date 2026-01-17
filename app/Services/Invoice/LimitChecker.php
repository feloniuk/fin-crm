<?php

namespace App\Services\Invoice;

use App\Models\OurCompany;
use App\Models\Setting;

class LimitChecker
{
    /**
     * Check if creating a new invoice would exceed the limit
     * Uses PAID invoices + external_sales + new invoice amount
     */
    public function checkLimit(OurCompany $company, float $invoiceTotal): array
    {
        if (!$company->hasLimit()) {
            return [
                'isExceeded' => false,
                'isWarning' => false,
                'remaining' => null,
                'percent' => null,
                'newTotal' => null,
                'limit' => null,
            ];
        }

        // ИЗМЕНЕНО: Используем оплаченные счета вместо всех счетов
        $yearlyPaid = $company->getYearlyPaidAmount();
        $externalSales = (float) $company->external_sales_amount;

        // Предполагаем, что новый счет будет оплачен
        $newTotal = $yearlyPaid + $externalSales + $invoiceTotal;

        $limit = $company->getEffectiveLimit();

        // Если есть ручное переопределение, используем его для базы расчета
        if ($company->remaining_limit_override !== null) {
            $currentRemaining = (float) $company->remaining_limit_override;
            $remaining = $currentRemaining - $invoiceTotal;
            $percent = $limit > 0 ? (($limit - $remaining) / $limit) * 100 : 0;
        } else {
            $remaining = $limit - $newTotal;
            $percent = $limit > 0 ? ($newTotal / $limit) * 100 : 0;
        }

        $warningThreshold = Setting::get('limits.warning_threshold', 90);

        return [
            'isExceeded' => $remaining < 0,
            'isWarning' => $percent >= $warningThreshold,
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

        $remaining = $company->getRemainingLimit();
        return $remaining !== null ? max(0, $remaining) : null;
    }

    public function getLimitUsagePercent(OurCompany $company): ?float
    {
        return $company->getLimitUsagePercent();
    }
}
