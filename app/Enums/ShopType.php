<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShopType: string implements HasLabel
{
    case HOROSHOP = 'horoshop';
    case PROM_UA = 'prom_ua';

    public function getLabel(): string
    {
        return match ($this) {
            self::HOROSHOP => 'Horoshop',
            self::PROM_UA => 'Prom.ua',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HOROSHOP => 'success',
            self::PROM_UA => 'info',
        };
    }
}
