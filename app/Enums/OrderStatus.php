<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor
{
    case NEW = 'new';
    case PROCESSING = 'processing';
    case INVOICED = 'invoiced';
    case PAID = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'Нове',
            self::PROCESSING => 'В обробці',
            self::INVOICED => 'Виставлено рахунок',
            self::PAID => 'Оплачено',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => 'info',
            self::PROCESSING => 'warning',
            self::INVOICED => 'primary',
            self::PAID => 'success',
        };
    }

    public function canCreateInvoice(): bool
    {
        return $this === self::NEW;
    }
}
