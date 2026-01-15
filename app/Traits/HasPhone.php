<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasPhone
{
    /**
     * Format phone number for display
     */
    public function formatPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-digits
        $digits = preg_replace('/\D/', '', $phone);

        // Ukrainian format: +380 XX XXX XX XX
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return sprintf(
                '+%s %s %s %s %s',
                substr($digits, 0, 3),
                substr($digits, 3, 2),
                substr($digits, 5, 3),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }

        // If starts with 0, assume Ukrainian number without country code
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return sprintf(
                '+380 %s %s %s %s',
                substr($digits, 1, 2),
                substr($digits, 3, 3),
                substr($digits, 6, 2),
                substr($digits, 8, 2)
            );
        }

        return $phone;
    }

    /**
     * Normalize phone to standard format for storage
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        // Convert 0XX to +380XX
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+380' . substr($digits, 1);
        }

        // Already has country code
        if (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            return '+' . $digits;
        }

        return $phone;
    }

    /**
     * Get formatted phone attribute
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->formatPhone($this->phone ?? ''),
        );
    }

    /**
     * Get formatted customer phone attribute
     */
    protected function formattedCustomerPhone(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->formatPhone($this->customer_phone ?? ''),
        );
    }
}
