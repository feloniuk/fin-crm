<?php

namespace App\Services\Invoice;

class InvoiceCalculator
{
    public function calculateSubtotal(array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            $lineSubtotal = $quantity * $unitPrice;

            // Розрахунок знижки залежно від типу
            $discountType = $item['discount_type'] ?? null;
            $discountValue = (float) ($item['discount_value'] ?? 0);

            $discountAmount = 0;
            if ($discountType === 'percent' && $discountValue > 0) {
                $discountAmount = $lineSubtotal * ($discountValue / 100);
            } elseif ($discountType === 'fixed' && $discountValue > 0) {
                $discountAmount = min($discountValue, $lineSubtotal);
            }

            $lineTotal = $lineSubtotal - $discountAmount;
            $subtotal += $lineTotal;
        }

        return round($subtotal, 2);
    }

    public function calculateVat(float $subtotal, bool $withVat): float
    {
        if (!$withVat) {
            return 0;
        }

        return round($subtotal * 0.20, 2);
    }

    public function calculateTotal(float $subtotal, float $vat, float $discountValue = 0): float
    {
        return round($subtotal + $vat - $discountValue, 2);
    }

    public function calculateItemDiscount(float $subtotal, ?string $discountType, float $discountValue): float
    {
        if (!$discountType || $discountValue <= 0) {
            return 0;
        }

        if ($discountType === 'percent') {
            return round($subtotal * ($discountValue / 100), 2);
        }

        // fixed
        return round(min($discountValue, $subtotal), 2);
    }

    public function calculateInvoice(array $items, bool $withVat, float $globalDiscount = 0): array
    {
        $subtotal = $this->calculateSubtotal($items);
        $vat = $this->calculateVat($subtotal, $withVat);
        $total = $this->calculateTotal($subtotal, $vat, $globalDiscount);

        return [
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total' => $total,
        ];
    }
}
