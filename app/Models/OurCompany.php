<?php

namespace App\Models;

use App\Enums\CompanyType;
use App\Enums\TaxSystem;
use App\Traits\HasMoney;
use App\Traits\HasPhone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OurCompany extends Model
{
    use HasFactory, HasMoney, HasPhone;

    protected $fillable = [
        'name',
        'type',
        'tax_system',
        'edrpou_ipn',
        'address',
        'phone',
        'email',
        'bank_details',
        'signatory_name',
        'signatory_position',
        'annual_limit',
        'external_sales_amount',
        'remaining_limit_override',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'tax_system' => TaxSystem::class,
            'bank_details' => 'array',
            'annual_limit' => 'decimal:2',
            'external_sales_amount' => 'decimal:2',
            'remaining_limit_override' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFop($query)
    {
        return $query->where('type', CompanyType::FOP);
    }

    public function scopeTov($query)
    {
        return $query->where('type', CompanyType::TOV);
    }

    public function scopeWithSingleTax($query)
    {
        return $query->where('tax_system', TaxSystem::SINGLE_TAX);
    }

    // Accessors

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type->getLabel() . ' ' . $this->name,
        );
    }

    protected function bankName(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->bank_details, 'bank_name'),
        );
    }

    protected function iban(): Attribute
    {
        return Attribute::make(
            get: fn () => data_get($this->bank_details, 'iban'),
        );
    }

    // Business Logic

    /**
     * Get the global limit for this company type from settings
     */
    public function getGlobalLimit(): ?float
    {
        $settingKey = match ($this->type) {
            CompanyType::FOP => 'limits.fop.max_amount',
            CompanyType::TOV => 'limits.tov.max_amount',
        };

        return Setting::get($settingKey);
    }

    /**
     * Check if company has a limit (global or individual)
     */
    public function hasLimit(): bool
    {
        return $this->getEffectiveLimit() !== null;
    }

    /**
     * Get the effective limit: individual annual_limit has priority, otherwise use global limit
     */
    public function getEffectiveLimit(): ?float
    {
        // Приоритет: индивидуальный лимит > глобальный лимит
        if ($this->annual_limit && $this->annual_limit > 0) {
            return (float) $this->annual_limit;
        }

        return $this->getGlobalLimit();
    }

    public function hasVat(): bool
    {
        return $this->tax_system === TaxSystem::VAT;
    }

    public function getYearlyInvoicedAmount(?int $year = null): float
    {
        $year = $year ?? now()->year;

        return $this->invoices()
            ->whereYear('invoice_date', $year)
            ->sum('total');
    }

    public function getYearlyPaidAmount(?int $year = null): float
    {
        $year = $year ?? now()->year;

        return $this->invoices()
            ->whereYear('invoice_date', $year)
            ->where('is_paid', true)
            ->sum('total');
    }

    /**
     * Get total amount used: paid invoices + external sales
     */
    public function getTotalUsedAmount(?int $year = null): float
    {
        if ($this->remaining_limit_override !== null) {
            $effectiveLimit = $this->getEffectiveLimit();
            return $effectiveLimit ? $effectiveLimit - (float) $this->remaining_limit_override : 0;
        }

        return $this->getYearlyPaidAmount($year) + (float) $this->external_sales_amount;
    }

    /**
     * Get remaining limit
     * Formula: if remaining_limit_override is set, use it; otherwise calculate from effective_limit - paid - external_sales
     */
    public function getRemainingLimit(?int $year = null): ?float
    {
        $effectiveLimit = $this->getEffectiveLimit();

        if ($effectiveLimit === null) {
            return null;
        }

        // Если есть ручное переопределение - использовуем его
        if ($this->remaining_limit_override !== null) {
            return (float) $this->remaining_limit_override;
        }

        // Иначе рассчитываем: Лимит - Оплаченные рахунки - Зовнішні продажи
        $paidAmount = $this->getYearlyPaidAmount($year);
        $externalSales = (float) $this->external_sales_amount;

        return $effectiveLimit - $paidAmount - $externalSales;
    }

    /**
     * Get limit usage percentage based on paid amounts and external sales
     */
    public function getLimitUsagePercent(?int $year = null): ?float
    {
        $effectiveLimit = $this->getEffectiveLimit();

        if ($effectiveLimit === null || $effectiveLimit <= 0) {
            return null;
        }

        // Если есть override - считаем от него
        if ($this->remaining_limit_override !== null) {
            $used = $effectiveLimit - (float) $this->remaining_limit_override;
            return ($used / $effectiveLimit) * 100;
        }

        // Иначе считаем от оплаченных + внешних
        $paidAmount = $this->getYearlyPaidAmount($year);
        $externalSales = (float) $this->external_sales_amount;
        $totalUsed = $paidAmount + $externalSales;

        return ($totalUsed / $effectiveLimit) * 100;
    }

    public function isLimitExceeded(?int $year = null): bool
    {
        $remaining = $this->getRemainingLimit($year);

        return $remaining !== null && $remaining < 0;
    }

    public function isLimitWarning(?int $year = null): bool
    {
        $percent = $this->getLimitUsagePercent($year);
        $warningThreshold = Setting::get('limits.warning_threshold', 90);

        return $percent !== null && $percent >= $warningThreshold;
    }
}
