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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CompanyType::class,
            'tax_system' => TaxSystem::class,
            'bank_details' => 'array',
            'annual_limit' => 'decimal:2',
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

    public function hasLimit(): bool
    {
        return $this->tax_system === TaxSystem::SINGLE_TAX;
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

    public function getRemainingLimit(?int $year = null): ?float
    {
        if (!$this->hasLimit() || !$this->annual_limit) {
            return null;
        }

        return $this->annual_limit - $this->getYearlyInvoicedAmount($year);
    }

    public function getLimitUsagePercent(?int $year = null): ?float
    {
        if (!$this->hasLimit() || !$this->annual_limit) {
            return null;
        }

        $invoiced = $this->getYearlyInvoicedAmount($year);

        return ($invoiced / $this->annual_limit) * 100;
    }

    public function isLimitExceeded(?int $year = null): bool
    {
        $remaining = $this->getRemainingLimit($year);

        return $remaining !== null && $remaining < 0;
    }

    public function isLimitWarning(?int $year = null): bool
    {
        $percent = $this->getLimitUsagePercent($year);

        return $percent !== null && $percent >= 90;
    }
}
