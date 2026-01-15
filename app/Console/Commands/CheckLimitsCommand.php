<?php

namespace App\Console\Commands;

use App\Enums\CompanyType;
use App\Enums\TaxSystem;
use App\Models\OurCompany;
use Illuminate\Console\Command;

class CheckLimitsCommand extends Command
{
    protected $signature = 'limits:check';

    protected $description = 'Перевірити ліміти ФОП компаній та вивести попередження';

    public function handle()
    {
        $this->info('🔍 Перевіряємо ліміти компаній...');

        $companies = OurCompany::active()
            ->where('tax_system', TaxSystem::SINGLE_TAX)
            ->get();

        if ($companies->isEmpty()) {
            $this->info('ℹ️ Немає ФОП компаній з лімітами');
            return self::SUCCESS;
        }

        $warning_count = 0;
        $exceeded_count = 0;

        foreach ($companies as $company) {
            $isExceeded = $company->isLimitExceeded();
            $isWarning = $company->isLimitWarning();
            $percent = $company->getLimitUsagePercent();
            $remaining = $company->getRemainingLimit();

            if ($isExceeded) {
                $this->warn("❌ ПЕРЕВИЩЕНО: {$company->name}");
                $this->line("   Ліміт: " . number_format($company->annual_limit, 2, ',', ' ') . ' грн');
                $this->line("   Виписано: " . number_format($company->getYearlyInvoicedAmount(), 2, ',', ' ') . ' грн');
                $this->line("   Перевищено на: " . number_format(abs($remaining), 2, ',', ' ') . ' грн');
                $exceeded_count++;
            } elseif ($isWarning) {
                $this->comment("⚠️ ПОПЕРЕДЖЕННЯ: {$company->name}");
                $this->line("   Використано: {$percent}% від ліміту");
                $this->line("   Залишок: " . number_format($remaining, 2, ',', ' ') . ' грн');
                $warning_count++;
            } else {
                $this->info("✅ OK: {$company->name}");
                $this->line("   Використано: {$percent}% від ліміту");
            }

            $this->line('');
        }

        $this->info("📊 Итого:");
        $this->line("   ✅ Нормально: " . ($companies->count() - $warning_count - $exceeded_count));
        $this->line("   ⚠️ Попередження: {$warning_count}");
        $this->line("   ❌ Перевищено: {$exceeded_count}");

        return self::SUCCESS;
    }
}
