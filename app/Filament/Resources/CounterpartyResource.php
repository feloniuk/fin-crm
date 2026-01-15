<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CounterpartyResource\Pages;
use App\Models\Counterparty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CounterpartyResource extends Resource
{
    protected static ?string $model = Counterparty::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Контрагент';

    protected static ?string $pluralModelLabel = 'Контрагенти';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Назва/ПІБ')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('edrpou_ipn')
                            ->label('ЄДРПОУ/ІПН')
                            ->maxLength(10)
                            ->length(10),

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

                        Forms\Components\Toggle::make('is_auto_created')
                            ->label('Автоматично створено')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Статистика')
                    ->schema([
                        Forms\Components\Placeholder::make('invoices_count')
                            ->label('Рахунків')
                            ->content(fn (?Counterparty $record): string =>
                                $record?->invoices()->count() ?? '0'
                            ),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Створено')
                            ->content(fn (?Counterparty $record): string =>
                                $record?->created_at?->format('d.m.Y H:i') ?? '-'
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
                    ->label('Назва/ПІБ')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('edrpou_ipn')
                    ->label('ЄДРПОУ/ІПН')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_auto_created')
                    ->label('Автостворено')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Рахунків')
                    ->counts('invoices')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_auto_created')
                    ->label('Автостворено'),
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
            'index' => Pages\ListCounterparties::route('/'),
            'create' => Pages\CreateCounterparty::route('/create'),
            'view' => Pages\ViewCounterparty::route('/{record}'),
            'edit' => Pages\EditCounterparty::route('/{record}/edit'),
        ];
    }
}
