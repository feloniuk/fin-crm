<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                        Forms\Components\TextInput::make('order_number')
                            ->label('№ замовлення')
                            ->disabled(),

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

                Forms\Components\Section::make('Компанія та ПДВ')
                    ->description('Виберіть компанію-виконавця та режим оподаткування для цього замовлення')
                    ->schema([
                        Forms\Components\Select::make('our_company_id')
                            ->label('Компанія-виконавець')
                            ->relationship('ourCompany', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Order $record): bool => $record->status->canCreateInvoice())
                            ->helperText('Компанія, яка буде виконувати замовлення')
                            ->visibleOn('edit'),

                        Forms\Components\Toggle::make('with_vat')
                            ->label('З ПДВ')
                            ->required(fn (Order $record): bool => $record->status->canCreateInvoice())
                            ->helperText('Чи буде рахунок з ПДВ (20%)')
                            ->visibleOn('edit'),
                    ])
                    ->columns(2)
                    ->visibleOn('edit')
                    ->visible(fn (Order $record): bool => $record->status->canCreateInvoice()),

                Forms\Components\Section::make('Товари замовлення')
                    ->schema([
                        Forms\Components\Placeholder::make('items_count')
                            ->label('Кількість позицій')
                            ->content(fn (Order $record): string =>
                                $record->items()->count() ?? '0'
                            ),

                        Forms\Components\Placeholder::make('items_subtotal')
                            ->label('Сума без знижок')
                            ->content(fn (Order $record): string =>
                                number_format($record->subtotal ?? 0, 2, ',', ' ') . ' грн'
                            ),

                        Forms\Components\Placeholder::make('items_discount')
                            ->label('Знижки')
                            ->content(fn (Order $record): string =>
                                number_format($record->discount_total ?? 0, 2, ',', ' ') . ' грн'
                            ),

                        Forms\Components\Placeholder::make('items_total')
                            ->label('Всього')
                            ->content(fn (Order $record): string =>
                                number_format(($record->subtotal ?? 0) - ($record->discount_total ?? 0), 2, ',', ' ') . ' грн'
                            ),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['shop', 'invoice', 'ourCompany']))
            ->columns([
                Tables\Columns\TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order_number')
                    ->label('№ замовлення')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID в магазині')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Tables\Columns\TextColumn::make('ourCompany.name')
                    ->label('Компанія')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('with_vat')
                    ->label('ПДВ')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),

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

                Tables\Filters\Filter::make('without_company')
                    ->label('Без компанії')
                    ->query(fn ($query) => $query->whereNull('our_company_id')),

                Tables\Filters\SelectFilter::make('our_company')
                    ->label('Компанія')
                    ->relationship('ourCompany', 'name'),

                Tables\Filters\TernaryFilter::make('with_vat')
                    ->label('ПДВ')
                    ->placeholder('Всі')
                    ->trueLabel('З ПДВ')
                    ->falseLabel('Без ПДВ'),

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
                Tables\Actions\Action::make('createInvoice')
                    ->label('Створити рахунок')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->canCreateInvoice())
                    ->url(fn (Order $record) => route('filament.admin.resources.invoices.create', [
                        'order_id' => $record->id,
                    ])),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('assignCompanyAndVat')
                        ->label('Призначити компанію та ПДВ')
                        ->icon('heroicon-o-building-office')
                        ->color('primary')
                        ->form([
                            Forms\Components\Select::make('our_company_id')
                                ->label('Компанія-виконавець')
                                ->options(
                                    \App\Models\OurCompany::active()
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->helperText('Компанія, яка буде виконувати замовлення'),

                            Forms\Components\Toggle::make('with_vat')
                                ->label('З ПДВ')
                                ->required()
                                ->helperText('Чи будуть рахунки з ПДВ (20%)'),
                        ])
                        ->action(function ($records, array $data) {
                            $updated = 0;
                            foreach ($records as $record) {
                                // Оновлюємо тільки замовлення без рахунків
                                if (!$record->invoice) {
                                    $record->update([
                                        'our_company_id' => $data['our_company_id'],
                                        'with_vat' => $data['with_vat'],
                                    ]);
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->title("Оновлено {$updated} замовлень")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Призначити компанію та ПДВ')
                        ->modalDescription('Буде оновлено тільки замовлення без створених рахунків.')
                        ->modalSubmitActionLabel('Призначити'),
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
