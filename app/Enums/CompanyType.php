<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CompanyType: string implements HasLabel
{
    case FOP = 'fop';
    case TOV = 'tov';

    public function getLabel(): string
    {
        return match ($this) {
            self::FOP => 'ФОП',
            self::TOV => 'ТОВ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FOP => 'warning',
            self::TOV => 'primary',
        };
    }
}
