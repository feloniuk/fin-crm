<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case NONE = 'none';
    case PERCENT = 'percent';
    case FIXED = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NONE => 'Без знижки',
            self::PERCENT => 'Відсотки (%)',
            self::FIXED => 'Фіксована сума (грн)',
        };
    }

    public function calculate(float $amount, float $discountValue): float
    {
        return match ($this) {
            self::NONE => 0,
            self::PERCENT => $amount * ($discountValue / 100),
            self::FIXED => $discountValue,
        };
    }
}
