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

                        Forms\Components\Select::make('counterparty_id')
                            ->label('Контрагент')
                            ->relationship('counterparty', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Виберіть контрагента')
                            ->helperText('Пов\'язаний контрагент (опціонально)')
                            ->visibleOn('edit'),
                    ]),

                Forms\Components\Section::make('Дані доставки')
                    ->schema([
                        Forms\Components\TextInput::make('delivery_name')
                            ->label('ПІБ одержувача')
                            ->disabled(),

                        Forms\Components\TextInput::make('delivery_phone')
                            ->label('Телефон одержувача')
                            ->disabled(),

                        Forms\Components\TextInput::make('delivery_city')
                            ->label('Місто доставки')
                            ->disabled(),

                        Forms\Components\TextInput::make('delivery_type')
                            ->label('Тип доставки')
                            ->disabled(),

                        Forms\Components\Textarea::make('delivery_address')
                            ->label('Адреса доставки')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Оплата та статус')
                    ->schema([
                        Forms\Components\TextInput::make('payment_type')
                            ->label('Тип оплати')
                            ->disabled(),

                        Forms\Components\Toggle::make('payed')
                            ->label('Оплачено')
                            ->disabled(),

                        Forms\Components\TextInput::make('currency')
                            ->label('Валюта')
                            ->disabled(),

                        Forms\Components\TextInput::make('api_status')
                            ->label('Статус в API')
                            ->disabled(),

                        Forms\Components\Textarea::make('manager_comment')
                            ->label('Коментар менеджера')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

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
                            ->live()
                            ->helperText('Компанія, яка буде виконувати замовлення'),

                        Forms\Components\Toggle::make('with_vat')
                            ->label('З ПДВ')
                            ->default(false)
                            ->live()
                            ->dehydrateStateUsing(fn ($state) => (bool) $state)
                            ->helperText('Чи буде рахунок з ПДВ (20%)'),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('createInvoice')
                                ->label('Створити рахунок і відкрити наступний')
                                ->icon('heroicon-o-document-plus')
                                ->color('success')
                                ->size('lg')
                                ->visible(fn (Forms\Get $get, ?Order $record): bool =>
                                    !empty($get('our_company_id')) &&
                                    $get('with_vat') !== null &&
                                    $record &&
                                    !$record->invoice &&
                                    $record->items()->exists()
                                )
                                ->action(function (Order $record, Forms\Get $get, $livewire) {
                                    // 1. Save order data
                                    $record->update([
                                        'our_company_id' => $get('our_company_id'),
                                        'with_vat' => (bool) $get('with_vat'),
                                    ]);
                                    $record->refresh();

                                    // 2. Create invoice
                                    $company = \App\Models\OurCompany::find($get('our_company_id'));
                                    $counterparty = $record->counterparty;

                                    if (!$counterparty && $record->customer_phone) {
                                        $counterparty = \App\Models\Counterparty::where('phone', $record->customer_phone)->first();
                                    }
                                    if (!$counterparty) {
                                        $counterparty = \App\Models\Counterparty::create([
                                            'name' => $record->customer_name,
                                            'phone' => $record->customer_phone,
                                            'address' => $record->delivery_address,
                                            'is_auto_created' => true,
                                        ]);
                                    }

                                    $items = $record->items->map(fn ($item) => [
                                        'name' => $item->name,
                                        'quantity' => (float) $item->quantity,
                                        'unit' => $item->unit ?? 'шт.',
                                        'unit_price' => (float) $item->unit_price,
                                        'discount_type' => $item->discount_type ?? '',
                                        'discount_value' => (float) ($item->discount_value ?? 0),
                                        'total' => (float) $item->total,
                                    ])->toArray();

                                    $action = app(\App\Actions\Invoice\CreateInvoiceAction::class);
                                    $invoice = $action->execute(
                                        company: $company,
                                        counterparty: $counterparty,
                                        items: $items,
                                        withVat: (bool) $get('with_vat'),
                                        order: $record,
                                        isPaid: (bool) $record->payed,
                                    );

                                    \Filament\Notifications\Notification::make()
                                        ->success()
                                        ->title('Рахунок створено')
                                        ->body("Рахунок {$invoice->invoice_number} успішно створено")
                                        ->send();

                                    // 3. Find next order
                                    $nextOrder = Order::where('status', \App\Enums\OrderStatus::NEW->value)
                                        ->doesntHave('invoice')
                                        ->where(function ($query) {
                                            $query->whereNotNull('our_company_id')
                                                  ->whereNotNull('with_vat');
                                        })
                                        ->where('id', '>', $record->id)
                                        ->orderBy('id', 'asc')
                                        ->first();

                                    if (!$nextOrder) {
                                        $nextOrder = Order::where('status', \App\Enums\OrderStatus::NEW->value)
                                            ->doesntHave('invoice')
                                            ->where('id', '>', $record->id)
                                            ->orderBy('id', 'asc')
                                            ->first();
                                    }

                                    // 4. Redirect
                                    if ($nextOrder) {
                                        $livewire->redirect(static::getUrl('edit', ['record' => $nextOrder]));
                                    } else {
                                        \Filament\Notifications\Notification::make()
                                            ->info()
                                            ->title('Готово')
                                            ->body('Немає більше замовлень для обробки')
                                            ->send();
                                        $livewire->redirect(static::getUrl('index'));
                                    }
                                }),
                        ])->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),

                // VIEW mode - readonly items
                Forms\Components\Section::make('Товари замовлення')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Товар')
                                    ->disabled()
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Кількість')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) =>
                                        is_numeric($state) ? rtrim(rtrim(number_format((float)$state, 3, ',', ' '), '0'), ',') : $state
                                    )
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Ціна')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) =>
                                        number_format((float)($state ?? 0), 2, ',', ' ') . ' грн'
                                    )
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('discount_display')
                                    ->label('Знижка')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state, $record) =>
                                        match($record?->discount_type) {
                                            'percent' => number_format($record->discount_value ?? 0, 0) . '%',
                                            'fixed' => number_format($record->discount_value ?? 0, 2, ',', ' ') . ' грн',
                                            default => '—'
                                        }
                                    )
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('total')
                                    ->label('Сума')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) =>
                                        number_format((float)($state ?? 0), 2, ',', ' ') . ' грн'
                                    )
                                    ->columnSpan(2),
                            ])
                            ->columns(10)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string =>
                                !empty($state['name'])
                                    ? $state['name'] . ' — ' . number_format((float)($state['total'] ?? 0), 2, ',', ' ') . ' грн'
                                    : 'Товар'
                            )
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),

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
                    ->columns(3)
                    ->visibleOn('view'),

                // EDIT mode - editable items
                Forms\Components\Section::make('Товари замовлення')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->itemLabel(function (array $state): ?string {
                                if (empty($state['name'])) {
                                    return 'Новий товар';
                                }
                                $quantity = (float) ($state['quantity'] ?? 0);
                                $unitPrice = (float) ($state['unit_price'] ?? 0);
                                $discountType = $state['discount_type'] ?? '';
                                $discountValue = (float) ($state['discount_value'] ?? 0);

                                $subtotal = $quantity * $unitPrice;
                                $discountAmount = 0;
                                if ($discountType === 'percent' && $discountValue > 0) {
                                    $discountAmount = $subtotal * ($discountValue / 100);
                                } elseif ($discountType === 'fixed' && $discountValue > 0) {
                                    $discountAmount = min($discountValue, $subtotal);
                                }
                                $total = max(0, $subtotal - $discountAmount);

                                return $state['name'] . ' — ' . number_format($total, 2, ',', ' ') . ' грн';
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Товар з каталогу')
                                    ->options(\App\Models\Product::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                                        if ($state) {
                                            $product = \App\Models\Product::find($state);
                                            if ($product) {
                                                $set('name', $product->name);
                                            }
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('name')
                                    ->label('Назва товару')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Кількість')
                                    ->numeric()
                                    ->step(0.001)
                                    ->required()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) =>
                                        self::recalculateOrderItem($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Ціна')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) =>
                                        self::recalculateOrderItem($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Select::make('discount_type')
                                    ->label('Тип знижки')
                                    ->options([
                                        '' => 'Без знижки',
                                        'percent' => 'Відсоток (%)',
                                        'fixed' => 'Сума (грн)',
                                    ])
                                    ->default('')
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get) {
                                        $set('discount_value', 0);
                                        self::recalculateOrderItem($set, $get);
                                    })
                                    ->columnSpan(1),

                                Forms\Components\TextInput::make('discount_value')
                                    ->label('Знижка')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0)
                                    ->visible(fn (Forms\Get $get): bool => !empty($get('discount_type')))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) =>
                                        self::recalculateOrderItem($set, $get)
                                    )
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('total_display')
                                    ->label('Сума')
                                    ->content(function (Forms\Get $get): string {
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

                                        return number_format($total, 2, ',', ' ') . ' грн';
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(10)
                            ->collapsible()
                            ->live()
                            ->reorderable()
                            ->addable(fn (?Order $record): bool => !$record?->invoice)
                            ->deletable(fn (?Order $record): bool => !$record?->invoice)
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['shop', 'invoice', 'ourCompany', 'counterparty', 'items']))
            ->recordUrl(fn (Order $record) => static::getUrl('edit', ['record' => $record]))
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

                Tables\Columns\TextColumn::make('delivery_city')
                    ->label('Місто')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('delivery_type')
                    ->label('Доставка')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('counterparty.name')
                    ->label('Контрагент')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

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
                Tables\Actions\Action::make('viewItems')
                    ->label('')
                    ->tooltip('Переглянути товари')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('gray')
                    ->modalHeading(fn (Order $record) => "Товари замовлення #{$record->order_number}")
                    ->modalContent(fn (Order $record) => new \Illuminate\Support\HtmlString(
                        $record->items->count() > 0
                            ? '<table class="w-full text-sm"><thead class="bg-gray-50 dark:bg-gray-800"><tr>' .
                              '<th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Товар</th>' .
                              '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">К-сть</th>' .
                              '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Ціна</th>' .
                              '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Знижка</th>' .
                              '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Сума</th>' .
                              '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">' .
                              $record->items->map(fn ($item) =>
                                  '<tr><td class="px-3 py-2 text-gray-900 dark:text-gray-100">' . e($item->name) . '</td>' .
                                  '<td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">' . number_format($item->quantity, 2, ',', ' ') . '</td>' .
                                  '<td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">' . number_format($item->unit_price, 2, ',', ' ') . '</td>' .
                                  '<td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">' .
                                      match($item->discount_type) { 'percent' => number_format($item->discount_value, 0) . '%', 'fixed' => number_format($item->discount_value, 2, ',', ' ') . ' грн', default => '—' } .
                                  '</td>' .
                                  '<td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">' . number_format($item->total, 2, ',', ' ') . ' грн</td></tr>'
                              )->join('') .
                              '</tbody><tfoot class="bg-gray-50 dark:bg-gray-800 font-medium"><tr>' .
                              '<td colspan="4" class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">Всього:</td>' .
                              '<td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">' . number_format($record->items->sum('total'), 2, ',', ' ') . ' грн</td>' .
                              '</tr></tfoot></table>'
                            : '<div class="text-center py-6 text-gray-500 dark:text-gray-400">Товари не знайдено</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрити'),

                Tables\Actions\Action::make('createInvoice')
                    ->label('Рахунок')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->canCreateInvoice())
                    ->url(fn (Order $record) => route('filament.admin.resources.invoices.create', [
                        'order_id' => $record->id,
                    ])),

                Tables\Actions\EditAction::make(),
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
                                ->default(false)
                                ->helperText('Чи будуть рахунки з ПДВ (20%)'),
                        ])
                        ->action(function ($records, array $data) {
                            $updated = 0;
                            foreach ($records as $record) {
                                // Оновлюємо тільки замовлення без рахунків
                                if (!$record->invoice) {
                                    $record->update([
                                        'our_company_id' => $data['our_company_id'],
                                        'with_vat' => (bool) ($data['with_vat'] ?? false),
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
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    protected static function recalculateOrderItem(Forms\Set $set, Forms\Get $get): void
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
        $set('subtotal', $subtotal);
        $set('discount_amount', $discountAmount);
    }
}
