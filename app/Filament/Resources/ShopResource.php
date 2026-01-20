<?php

namespace App\Filament\Resources;

use App\Actions\Order\SyncOrdersAction;
use App\Enums\ShopType;
use App\Filament\Resources\ShopResource\Pages;
use App\Models\Shop;
use App\Services\Shop\ShopApiClientFactory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Магазин';

    protected static ?string $pluralModelLabel = 'Магазини';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Назва магазину')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Тип магазину')
                            ->options(ShopType::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активний')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('API налаштування Horoshop')
                    ->schema([
                        Forms\Components\TextInput::make('api_credentials.shop_url')
                            ->label('URL магазину')
                            ->placeholder('myshop.horoshop.ua')
                            ->helperText('Домен вашого магазину без https://')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('api_credentials.login')
                            ->label('API Логін')
                            ->placeholder('api')
                            ->helperText('Логін для API (зазвичай "api")')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('api_credentials.password')
                            ->label('API Пароль')
                            ->password()
                            ->revealable()
                            ->helperText('Пароль для доступу до API')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get): bool => $get('type') === ShopType::HOROSHOP->value),

                Forms\Components\Section::make('API налаштування Prom.ua')
                    ->schema([
                        Forms\Components\TextInput::make('api_credentials.api_token')
                            ->label('API Token')
                            ->password()
                            ->revealable()
                            ->helperText('Bearer токен для доступу до Prom.ua API')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get): bool => $get('type') === ShopType::PROM_UA->value),

                Forms\Components\Section::make('Статистика')
                    ->schema([
                        Forms\Components\Placeholder::make('last_synced_at')
                            ->label('Остання синхронізація')
                            ->content(fn (?Shop $record): string =>
                                $record?->last_synced_at?->format('d.m.Y H:i:s') ?? 'Ніколи'
                            ),

                        Forms\Components\Placeholder::make('orders_count')
                            ->label('Кількість замовлень')
                            ->content(fn (?Shop $record): string =>
                                $record?->orders()->count() ?? '0'
                            ),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активний')
                    ->boolean(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Замовлень')
                    ->counts('orders')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Остання синхронізація')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options(ShopType::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активний'),
            ])
            ->actions([
                Tables\Actions\Action::make('testConnection')
                    ->label('Тест')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (Shop $record) {
                        try {
                            $client = ShopApiClientFactory::make($record);
                            if ($client->testConnection()) {
                                Notification::make()
                                    ->success()
                                    ->title('Успіх')
                                    ->body("З'єднання з {$record->name} встановлено успішно!")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Помилка')
                                    ->body("Не вдалося з'єднатися: " . $client->getLastError())
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Помилка')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('syncOrders')
                    ->label('Синхронізувати')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Синхронізація замовлень')
                    ->modalDescription('Розпочати синхронізацію замовлень з цього магазину?')
                    ->action(function (Shop $record) {
                        try {
                            $action = app(SyncOrdersAction::class);
                            $action->execute($record);

                            Notification::make()
                                ->success()
                                ->title('Успіх')
                                ->body("Замовлення з {$record->name} успішно синхронізовано!")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Помилка синхронізації')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListShops::route('/'),
            'create' => Pages\CreateShop::route('/create'),
            'view' => Pages\ViewShop::route('/{record}'),
            'edit' => Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
