<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class BaseListRecords extends ListRecords
{
    public function table(Table $table): Table
    {
        return parent::table($table)->persistFiltersInSession();
    }
}
