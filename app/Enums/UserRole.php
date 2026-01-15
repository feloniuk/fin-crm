<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';

    public function getLabel(): string
    {
        return match ($this) {
            self::ADMIN => 'Адміністратор',
            self::MANAGER => 'Менеджер',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ADMIN => 'danger',
            self::MANAGER => 'info',
        };
    }

    public function canCreate(): bool
    {
        return $this === self::ADMIN;
    }

    public function canEdit(): bool
    {
        return $this === self::ADMIN;
    }

    public function canDelete(): bool
    {
        return $this === self::ADMIN;
    }
}
