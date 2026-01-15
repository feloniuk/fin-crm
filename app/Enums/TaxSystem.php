<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaxSystem: string implements HasLabel
{
    case SINGLE_TAX = 'single_tax';
    case VAT = 'vat';

    public function getLabel(): string
    {
        return match ($this) {
            self::SINGLE_TAX => 'Єдиний податок',
            self::VAT => 'ПДВ',
        };
    }

    public function hasLimit(): bool
    {
        return $this === self::SINGLE_TAX;
    }

    public function hasVat(): bool
    {
        return $this === self::VAT;
    }
}
