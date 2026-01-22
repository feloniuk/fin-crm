<?php

namespace App\Filament\Resources;

use App\Enums\CompanyType;
use App\Enums\TaxSystem;
use App\Filament\Resources\OurCompanyResource\Pages;
use App\Models\OurCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class OurCompanyResource extends Resource
{
    protected static ?string $model = OurCompany::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Компанія';

    protected static ?string $pluralModelLabel = 'Наші компанії';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Назва')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Тип')
                            ->options(CompanyType::class)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('tax_system')
                            ->label('Система оподаткування')
                            ->options(TaxSystem::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('edrpou_ipn')
                            ->label('ЄДРПОУ/ІПН')
                            ->required()
                            ->unique(OurCompany::class, 'edrpou_ipn', ignoreRecord: true)
                            ->length(10),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Контактна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Адреса')
                            ->maxLength(500),

                        Forms\Components\TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Банківські реквізити')
                    ->schema([
                        Forms\Components\Repeater::make('bank_details')
                            ->label('Банківські рахунки')
                            ->schema([
                                Forms\Components\TextInput::make('bank_name')
                                    ->label('Назва банку')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('mfo')
                                    ->label('МФО')
                                    ->length(6),

                                Forms\Components\TextInput::make('account')
                                    ->label('Рахунок')
                                    ->length(29),

                                Forms\Components\TextInput::make('iban')
                                    ->label('IBAN')
                                    ->length(29),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Підписант')
                    ->schema([
                        Forms\Components\TextInput::make('signatory_name')
                            ->label('ПІБ')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('signatory_position')
                            ->label('Посада')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ліміт обороту')
                    ->schema([
                        Forms\Components\Placeholder::make('global_limit_info')
                            ->label('Глобальні ліміти')
                            ->content(function (Get $get): string {
                                $type = $get('type');
                                if (!$type) {
                                    return 'Оберіть тип компанії';
                                }

                                $companyType = CompanyType::from($type);
                                $settingKey = match ($companyType) {
                                    CompanyType::FOP => 'limits.fop.max_amount',
                                    CompanyType::TOV => 'limits.tov.max_amount',
                                };

                                $globalLimit = \App\Models\Setting::get($settingKey, 0);
                                return "Глобальний ліміт для {$companyType->getLabel()}: " .
                                       number_format($globalLimit, 2, ',', ' ') . ' грн';
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('annual_limit')
                            ->label('Індивідуальний річний ліміт (грн)')
                            ->helperText('Залиште порожнім для використання глобального ліміту. Якщо встановлено - має пріоритет над глобальним.')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),

                        Forms\Components\TextInput::make('external_sales_amount')
                            ->label('Продажі поза системою (грн)')
                            ->helperText('Сума продаж, які не враховані в системі, але повинні враховуватися в ліміті')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('remaining_limit_override')
                            ->label('Ручне встановлення залишку (грн)')
                            ->helperText('Ручне переопределення залишку ліміту. Якщо встановлено - ігнорується автоматичний розрахунок.')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Статистика використання ліміту')
                    ->schema([
                        Forms\Components\Placeholder::make('effective_limit')
                            ->label('Діючий ліміт')
                            ->content(fn (?OurCompany $record): string =>
                                $record?->getEffectiveLimit()
                                    ? number_format($record->getEffectiveLimit(), 2, ',', ' ') . ' грн'
                                    : 'Не встановлено'
                            ),

                        Forms\Components\Placeholder::make('paid_year')
                            ->label('Оплачено в системі за рік')
                            ->content(fn (?OurCompany $record): string =>
                                $record
                                    ? number_format($record->getYearlyPaidAmount(), 2, ',', ' ') . ' грн'
                                    : '0,00 грн'
                            ),

                        Forms\Components\Placeholder::make('external_sales')
                            ->label('Продажі поза системою')
                            ->content(fn (?OurCompany $record): string =>
                                $record
                                    ? number_format($record->external_sales_amount ?? 0, 2, ',', ' ') . ' грн'
                                    : '0,00 грн'
                            ),

                        Forms\Components\Placeholder::make('total_used')
                            ->label('Всього використано')
                            ->content(fn (?OurCompany $record): string =>
                                $record
                                    ? number_format($record->getTotalUsedAmount(), 2, ',', ' ') . ' грн'
                                    : '0,00 грн'
                            ),

                        Forms\Components\Placeholder::make('remaining_limit')
                            ->label('Залишок ліміту')
                            ->content(function (?OurCompany $record) {
                                if (!$record) {
                                    return new HtmlString('0,00 грн');
                                }

                                $remaining = $record->getRemainingLimit();
                                if ($remaining === null) {
                                    return new HtmlString('Без ліміту');
                                }

                                $color = $remaining < 0 ? 'text-red-600' : 'text-green-600';
                                $value = number_format(abs($remaining), 2, ',', ' ') . ' грн';

                                $html = $remaining < 0
                                    ? "<span class='{$color}'>Перевищено на {$value}</span>"
                                    : "<span class='{$color}'>{$value}</span>";

                                return new HtmlString($html);
                            }),

                        Forms\Components\Placeholder::make('limit_usage')
                            ->label('Використання ліміту')
                            ->content(function (?OurCompany $record) {
                                if (!$record) {
                                    return new HtmlString('0%');
                                }

                                $percent = $record->getLimitUsagePercent();
                                if ($percent === null) {
                                    return new HtmlString('Без ліміту');
                                }

                                $color = match (true) {
                                    $percent >= 100 => 'text-red-600',
                                    $percent >= 90 => 'text-yellow-600',
                                    default => 'text-green-600',
                                };

                                $html = "<span class='{$color} font-semibold'>" .
                                        round($percent) . "%</span>";

                                return new HtmlString($html);
                            }),

                        Forms\Components\Placeholder::make('override_status')
                            ->label('Статус розрахунку')
                            ->content(fn (?OurCompany $record): string =>
                                $record?->remaining_limit_override !== null
                                    ? '🔒 Ручне встановлення активне'
                                    : '🔄 Автоматичний розрахунок'
                            )
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('tax_system')
                    ->label('Система')
                    ->badge(),

                Tables\Columns\TextColumn::make('edrpou_ipn')
                    ->label('ЄДРПОУ/ІПН')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Рахунків')
                    ->counts('invoices')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options(CompanyType::class),

                Tables\Filters\SelectFilter::make('tax_system')
                    ->label('Система')
                    ->options(TaxSystem::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активна'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
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
            'index' => Pages\ListOurCompanies::route('/'),
            'create' => Pages\CreateOurCompany::route('/create'),
            'view' => Pages\ViewOurCompany::route('/{record}'),
            'edit' => Pages\EditOurCompany::route('/{record}/edit'),
        ];
    }
}
