<?php

namespace App\Console\Commands;

use App\Models\OurCompany;
use Illuminate\Console\Command;

class CheckLimitsCommand extends Command
{
    protected $signature = 'limits:check';

    protected $description = 'Перевірити ліміти компаній та вивести попередження';

    public function handle()
    {
        $this->info('🔍 Перевіряємо ліміти компаній...');

        // ИЗМЕНЕНО: Получаем все активные компании с лимитами
        $companies = OurCompany::active()->get()->filter(function ($company) {
            return $company->hasLimit();
        });

        if ($companies->isEmpty()) {
            $this->info('ℹ️ Немає компаній з лімітами');
            return self::SUCCESS;
        }

        $warning_count = 0;
        $exceeded_count = 0;

        foreach ($companies as $company) {
            $effectiveLimit = $company->getEffectiveLimit();
            $remaining = $company->getRemainingLimit();
            $percent = $company->getLimitUsagePercent();
            $isExceeded = $company->isLimitExceeded();
            $isWarning = $company->isLimitWarning();

            // НОВОЕ: Показываем детальную информацию
            $limitType = $company->annual_limit ? 'індивідуальний' : 'глобальний';
            $hasOverride = $company->remaining_limit_override !== null ? ' [РУЧНЕ]' : '';

            if ($isExceeded) {
                $this->warn("❌ ПЕРЕВИЩЕНО: {$company->name}{$hasOverride}");
                $this->line("   Тип: {$company->type->getLabel()} ({$limitType} ліміт)");
                $this->line("   Ліміт: " . number_format($effectiveLimit, 2, ',', ' ') . ' грн');
                $this->line("   Оплачено в системі: " . number_format($company->getYearlyPaidAmount(), 2, ',', ' ') . ' грн');
                $this->line("   Продажі поза системою: " . number_format($company->external_sales_amount, 2, ',', ' ') . ' грн');
                $this->line("   Всього використано: " . number_format($company->getTotalUsedAmount(), 2, ',', ' ') . ' грн');
                $this->line("   Перевищено на: " . number_format(abs($remaining), 2, ',', ' ') . ' грн');
                $exceeded_count++;
            } elseif ($isWarning) {
                $this->comment("⚠️ ПОПЕРЕДЖЕННЯ: {$company->name}{$hasOverride}");
                $this->line("   Тип: {$company->type->getLabel()} ({$limitType} ліміт)");
                $this->line("   Використано: " . round($percent) . "% від ліміту");
                $this->line("   Залишок: " . number_format($remaining, 2, ',', ' ') . ' грн');
                $warning_count++;
            } else {
                $this->info("✅ OK: {$company->name}{$hasOverride}");
                $this->line("   Використано: " . round($percent) . "% від ліміту");
            }

            $this->line('');
        }

        $this->info("📊 Підсумок:");
        $this->line("   ✅ Нормально: " . ($companies->count() - $warning_count - $exceeded_count));
        $this->line("   ⚠️ Попередження: {$warning_count}");
        $this->line("   ❌ Перевищено: {$exceeded_count}");

        return self::SUCCESS;
    }
}
