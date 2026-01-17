<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit_items')
                ->label('Редагувати товари')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (Order $record): bool => $record->status->canCreateInvoice())
                ->form([
                    Forms\Components\Repeater::make('items')
                        ->label('Товари замовлення')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Товар')
                                ->relationship('product', 'name')
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Назва')
                                        ->required(),
                                ])
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('name')
                                ->label('Назва товару')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Кількість')
                                ->numeric()
                                ->step(0.001)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('subtotal', null);
                                    $set('discount_amount', null);
                                    $set('total', null);
                                })
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Ціна за одиницю')
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('subtotal', null);
                                    $set('discount_amount', null);
                                    $set('total', null);
                                })
                                ->columnSpan(1),

                            Forms\Components\Select::make('discount_type')
                                ->label('Тип знижки')
                                ->options([
                                    null => 'Без знижки',
                                    'percent' => 'Відсоток (%)',
                                    'fixed' => 'Сума (грн)',
                                ])
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('discount_amount', null);
                                    $set('total', null);
                                })
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('discount_value')
                                ->label('Значення знижки')
                                ->numeric()
                                ->step(0.01)
                                ->visible(fn (Forms\Get $get): bool => !empty($get('discount_type')))
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set) {
                                    $set('discount_amount', null);
                                    $set('total', null);
                                })
                                ->columnSpan(1),

                            Forms\Components\Placeholder::make('subtotal')
                                ->label('Сума без знижки')
                                ->content(function (Forms\Get $get): string {
                                    $quantity = (float) $get('quantity') ?? 0;
                                    $unitPrice = (float) $get('unit_price') ?? 0;
                                    $subtotal = $quantity * $unitPrice;
                                    return number_format($subtotal, 2, ',', ' ') . ' грн';
                                })
                                ->columnSpan(1),

                            Forms\Components\Placeholder::make('discount_amount')
                                ->label('Розмір знижки')
                                ->content(function (Forms\Get $get): string {
                                    $quantity = (float) $get('quantity') ?? 0;
                                    $unitPrice = (float) $get('unit_price') ?? 0;
                                    $subtotal = $quantity * $unitPrice;
                                    $discountType = $get('discount_type');
                                    $discountValue = (float) $get('discount_value') ?? 0;

                                    $discountAmount = 0;
                                    if ($discountType === 'percent') {
                                        $discountAmount = $subtotal * ($discountValue / 100);
                                    } elseif ($discountType === 'fixed') {
                                        $discountAmount = min($discountValue, $subtotal);
                                    }

                                    return number_format($discountAmount, 2, ',', ' ') . ' грн';
                                })
                                ->columnSpan(1),

                            Forms\Components\Placeholder::make('total')
                                ->label('Всього')
                                ->content(function (Forms\Get $get): string {
                                    $quantity = (float) $get('quantity') ?? 0;
                                    $unitPrice = (float) $get('unit_price') ?? 0;
                                    $subtotal = $quantity * $unitPrice;
                                    $discountType = $get('discount_type');
                                    $discountValue = (float) $get('discount_value') ?? 0;

                                    $discountAmount = 0;
                                    if ($discountType === 'percent') {
                                        $discountAmount = $subtotal * ($discountValue / 100);
                                    } elseif ($discountType === 'fixed') {
                                        $discountAmount = min($discountValue, $subtotal);
                                    }

                                    $total = max(0, $subtotal - $discountAmount);
                                    return number_format($total, 2, ',', ' ') . ' грн';
                                })
                                ->columnSpan(1),
                        ])
                        ->columns(3)
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->modalSubmitActionLabel('Зберегти')
                ->modalCancelActionLabel('Скасувати')
                ->modalHeading('Редагування товарів замовлення')
                ->action(function (Order $record, array $data): void {
                    $record->fill($data)->save();
                    Notification::make()
                        ->title('Товари оновлені')
                        ->body('Товари замовлення успішно оновлені')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('create_invoice')
                ->label('Створити рахунок')
                ->icon('heroicon-o-document')
                ->visible(fn (Order $record): bool => $record->canCreateInvoice())
                ->requiresConfirmation()
                ->action(fn (Order $record) => redirect(
                    route('filament.admin.resources.invoices.create', ['order_id' => $record->id])
                )),
        ];
    }
}
