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
            $discount = (float) ($item['discount'] ?? 0);

            $lineTotal = ($quantity * $unitPrice) - $discount;
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

    public function calculateItemTotal(float $quantity, float $unitPrice, float $discount): float
    {
        return round(($quantity * $unitPrice) - $discount, 2);
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
