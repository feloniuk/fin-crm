<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = '📦 Останні нові замовлення';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::where('status', OrderStatus::New)
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label("Клієнт")
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Сума')
                    ->formatStateUsing(fn ($state) =>
                        number_format($state, 2, ',', ' ') . ' грн'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
