<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasMoney
{
    /**
     * Format money value for display
     */
    public function formatMoney(float|int|null $amount): string
    {
        if ($amount === null) {
            return '0,00 грн';
        }

        return number_format($amount, 2, ',', ' ') . ' грн';
    }

    /**
     * Parse money string to float
     */
    public function parseMoney(string $value): float
    {
        $cleaned = preg_replace('/[^\d,.-]/', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return (float) $cleaned;
    }

    /**
     * Get formatted total attribute
     */
    protected function formattedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->formatMoney($this->total ?? 0),
        );
    }

    /**
     * Get formatted subtotal attribute
     */
    protected function formattedSubtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->formatMoney($this->subtotal ?? 0),
        );
    }

    /**
     * Get formatted VAT amount attribute
     */
    protected function formattedVatAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->formatMoney($this->vat_amount ?? 0),
        );
    }
}
