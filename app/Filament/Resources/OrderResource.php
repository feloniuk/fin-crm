<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Операції';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Замовлення';

    protected static ?string $pluralModelLabel = 'Замовлення';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Інформація про замовлення')
                    ->schema([
                        Forms\Components\TextInput::make('external_id')
                            ->label('ID в магазині')
                            ->disabled(),

                        Forms\Components\Select::make('shop_id')
                            ->label('Магазин')
                            ->relationship('shop', 'name')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Статус')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_amount')
                            ->label('Сума')
                            ->disabled()
                            ->formatStateUsing(fn (Order $record) =>
                                number_format($record->total_amount, 2, ',', ' ') . ' грн'
                            ),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Контактна інформація покупця')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('ПІБ')
                            ->disabled(),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Телефон')
                            ->disabled(),

                        Forms\Components\Textarea::make('customer_comment')
                            ->label('Коментар')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Дані API')
                    ->schema([
                        Forms\Components\Textarea::make('raw_data_display')
                            ->label('Raw Data (JSON)')
                            ->disabled()
                            ->formatStateUsing(fn (Order $record) =>
                                json_encode($record->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Дати')
                    ->schema([
                        Forms\Components\TextInput::make('synced_at')
                            ->label('Синхронізовано')
                            ->disabled()
                            ->formatStateUsing(fn (Order $record) =>
                                $record->synced_at?->format('d.m.Y H:i:s') ?? '-'
                            ),

                        Forms\Components\TextInput::make('created_at')
                            ->label('Створено')
                            ->disabled()
                            ->formatStateUsing(fn (Order $record) =>
                                $record->created_at?->format('d.m.Y H:i:s') ?? '-'
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID замовлення')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Покупець')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Телефон')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Сума')
                    ->formatStateUsing(fn (Order $record) =>
                        number_format($record->total_amount, 2, ',', ' ') . ' грн'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('invoice_exists')
                    ->label('Рахунок')
                    ->boolean()
                    ->getStateUsing(fn (Order $record) => $record->invoice()->exists())
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Синхронізовано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('shop')
                    ->label('Магазин')
                    ->relationship('shop', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderStatus::class),

                Tables\Filters\Filter::make('without_invoice')
                    ->label('Без рахунку')
                    ->query(fn ($query) => $query->doesntHave('invoice')),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Від'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('До'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($q, $date) => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($q, $date) => $q->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
