<?php

namespace App\Filament\Resources;

use App\Enums\DiscountType;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Counterparty;
use App\Models\Invoice;
use App\Models\OurCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Операції';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Рахунок';

    protected static ?string $pluralModelLabel = 'Рахунки';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Номер рахунку')
                            ->disabled()
                            ->visibleOn('edit'),

                        Forms\Components\DatePicker::make('invoice_date')
                            ->label('Дата рахунку')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('order_id')
                            ->label('Замовлення')
                            ->relationship('order', 'external_id')
                            ->getOptionLabelFromRecordUsing(fn ($record) =>
                                "#{$record->external_id} - {$record->customer_name} ({$record->total_amount})"
                            )
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $orderId = $get('order_id');
                                if ($orderId) {
                                    $order = \App\Models\Order::find($orderId);
                                    if ($order) {
                                        // Auto-fill company and VAT from Order if they're set
                                        if ($order->our_company_id) {
                                            $set('our_company_id', $order->our_company_id);
                                        }
                                        if ($order->with_vat !== null) {
                                            $set('with_vat', $order->with_vat);
                                        }
                                    }
                                }
                            })
                            ->visibleOn('create'),

                        Forms\Components\Select::make('our_company_id')
                            ->label('Наша компанія')
                            ->options(fn () => OurCompany::active()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Toggle::make('with_vat')
                            ->label('З ПДВ 20%')
                            ->default(false)
                            ->live()
                            ->helperText('Режим оподаткування для цього рахунку'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Контрагент-покупець')
                    ->schema([
                        Forms\Components\Select::make('counterparty_id')
                            ->label('Контрагент')
                            ->options(fn () => Counterparty::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Назва/ПІБ')
                                    ->required(),
                                Forms\Components\TextInput::make('edrpou_ipn')
                                    ->label('ЄДРПОУ/ІПН')
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('address')
                                    ->label('Адреса'),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Телефон')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $counterparty = Counterparty::create($data);
                                return $counterparty->id;
                            }),

                        Forms\Components\Placeholder::make('counterparty_info')
                            ->label('Інформація про контрагента')
                            ->content(function (Get $get): string {
                                $counterparty = Counterparty::find($get('counterparty_id'));
                                if (!$counterparty) {
                                    return 'Контрагент не вибраний';
                                }
                                $info = $counterparty->name;
                                if ($counterparty->edrpou_ipn) {
                                    $info .= ' (ЄДРПОУ: ' . $counterparty->edrpou_ipn . ')';
                                }
                                return $info;
                            })
                            ->visibleOn('edit'),
                    ]),

                Forms\Components\Section::make('Товари')
                    ->schema([
                        // Repeater для СТВОРЕННЯ (без relationship)
                        Forms\Components\Repeater::make('items')
                            ->label('Позиції рахунку')
                            ->visibleOn('create')
                            ->schema(self::getItemsSchema())
                            ->columns(12)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->live(),

                        // Repeater для РЕДАГУВАННЯ (з relationship)
                        Forms\Components\Repeater::make('items')
                            ->label('Позиції рахунку')
                            ->relationship()
                            ->visibleOn('edit')
                            ->schema(self::getItemsSchema())
                            ->columns(12)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull()
                            ->live(),
                    ]),

                Forms\Components\Section::make('Знижка на рахунок')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label('Тип знижки')
                            ->options(DiscountType::class)
                            ->default(DiscountType::NONE)
                            ->live(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label('Значення')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->visible(fn (Get $get) =>
                                !empty($get('discount_type'))
                            )
                            ->live(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Коментар')
                    ->schema([
                        Forms\Components\Textarea::make('comment')
                            ->label('Коментар до рахунку')
                            ->placeholder('Рахунок до договору №5 від 01.01.2025')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Статистика')
                    ->schema([
                        Forms\Components\Placeholder::make('subtotal')
                            ->label('Підсумок без ПДВ')
                            ->content(fn (Get $get) =>
                                number_format($get('subtotal') ?? 0, 2, ',', ' ') . ' грн'
                            )
                            ->visibleOn('edit'),

                        Forms\Components\Placeholder::make('vat_amount')
                            ->label('ПДВ 20%')
                            ->content(fn (Get $get) =>
                                number_format($get('vat_amount') ?? 0, 2, ',', ' ') . ' грн'
                            )
                            ->visibleOn(['edit'])
                            ->visible(fn (Get $get) => $get('with_vat')),

                        Forms\Components\Placeholder::make('total')
                            ->label('Всього до сплати')
                            ->content(fn (Get $get) =>
                                number_format($get('total') ?? 0, 2, ',', ' ') . ' грн'
                            )
                            ->visibleOn('edit'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ourCompany', 'counterparty']))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ourCompany.name')
                    ->label('Компанія')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('counterparty.name')
                    ->label('Контрагент')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Сума')
                    ->formatStateUsing(fn (Invoice $record) =>
                        number_format($record->total, 2, ',', ' ') . ' грн'
                    )
                    ->sortable(),

                Tables\Columns\IconColumn::make('with_vat')
                    ->label('ПДВ')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Оплачено')
                    ->boolean()
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Дата оплати')
                    ->date('d.m.Y')
                    ->sortable()
                    ->visible(fn () => false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('our_company')
                    ->label('Компанія')
                    ->relationship('ourCompany', 'name'),

                Tables\Filters\Filter::make('counterparty')
                    ->label('Контрагент')
                    ->form([
                        Forms\Components\Select::make('counterparty_id')
                            ->label('Контрагент')
                            ->relationship('counterparty', 'name')
                            ->searchable(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['counterparty_id'],
                            fn ($q) => $q->where('counterparty_id', $data['counterparty_id'])
                        );
                    }),

                Tables\Filters\Filter::make('invoice_date')
                    ->label('Період')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Від'),
                        Forms\Components\DatePicker::make('to')
                            ->label('До'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($q, $date) => $q->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn ($q, $date) => $q->whereDate('invoice_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('with_vat')
                    ->label('З ПДВ'),

                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Оплачено'),
            ])
            ->actions([
                Tables\Actions\Action::make('downloadExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Invoice $record) => $record->excel_path ? route('invoice.download-excel', $record) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record) => !empty($record->excel_path)),

                Tables\Actions\Action::make('downloadPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->url(fn (Invoice $record) => $record->pdf_path ? route('invoice.download-pdf', $record) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record) => !empty($record->pdf_path)),

                Tables\Actions\Action::make('markAsPaid')
                    ->label('Оплачено')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Invoice $record) {
                        $record->update([
                            'is_paid' => true,
                            'paid_at' => now(),
                        ]);
                    })
                    ->visible(fn (Invoice $record) => !$record->is_paid)
                    ->successNotificationTitle('Рахунок позначено як оплачено'),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    protected static function getItemsSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Товар')
                ->required()
                ->columnSpan(3),

            Forms\Components\TextInput::make('quantity')
                ->label('Кількість')
                ->required()
                ->numeric()
                ->default(1)
                ->step(0.001)
                ->columnSpan(1)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, Get $get) =>
                    self::recalculateItem($set, $get)
                ),

            Forms\Components\TextInput::make('unit')
                ->label('Од.')
                ->default('шт.')
                ->columnSpan(1),

            Forms\Components\TextInput::make('unit_price')
                ->label('Ціна')
                ->required()
                ->numeric()
                ->step(0.01)
                ->columnSpan(2)
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, Get $get) =>
                    self::recalculateItem($set, $get)
                ),

            Forms\Components\Select::make('discount_type')
                ->label('Тип знижки')
                ->options([
                    '' => 'Без знижки',
                    'percent' => 'Відсоток (%)',
                    'fixed' => 'Сума (грн)',
                ])
                ->default('')
                ->columnSpan(2)
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get) {
                    $set('discount_value', 0);
                    self::recalculateItem($set, $get);
                }),

            Forms\Components\TextInput::make('discount_value')
                ->label(fn (Get $get) => $get('discount_type') === 'percent' ? 'Знижка (%)' : 'Знижка (грн)')
                ->numeric()
                ->step(0.01)
                ->default(0)
                ->columnSpan(1)
                ->visible(fn (Get $get): bool => !empty($get('discount_type')))
                ->live(debounce: 500)
                ->afterStateUpdated(fn (Set $set, Get $get) =>
                    self::recalculateItem($set, $get)
                ),

            Forms\Components\TextInput::make('total')
                ->label('Сума')
                ->disabled()
                ->dehydrated()
                ->formatStateUsing(fn ($state) =>
                    number_format((float) ($state ?? 0), 2, ',', ' ')
                )
                ->columnSpan(2),
        ];
    }

    protected static function recalculateItem(Set $set, Get $get): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = (float) ($get('unit_price') ?? 0);
        $discountType = $get('discount_type');
        $discountValue = (float) ($get('discount_value') ?? 0);

        $subtotal = $quantity * $unitPrice;

        $discountAmount = 0;
        if ($discountType === 'percent' && $discountValue > 0) {
            $discountAmount = $subtotal * ($discountValue / 100);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $discountAmount = min($discountValue, $subtotal);
        }

        $total = max(0, $subtotal - $discountAmount);
        $set('total', $total);
    }
}
