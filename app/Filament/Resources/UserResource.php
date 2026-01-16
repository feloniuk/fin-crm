<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Користувач';

    protected static ?string $pluralModelLabel = 'Користувачі';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основна інформація')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ім\'я')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8)
                            ->helperText(fn (string $context): ?string =>
                                $context === 'edit' ? 'Залиште порожнім, щоб не змінювати пароль' : null
                            ),

                        Forms\Components\Select::make('role')
                            ->label('Роль')
                            ->options(UserRole::class)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Статистика')
                    ->schema([
                        Forms\Components\Placeholder::make('email_verified_at')
                            ->label('Email підтверджений')
                            ->content(fn (?User $record): string =>
                                $record?->email_verified_at?->format('d.m.Y H:i:s') ?? 'Не підтверджений'
                            ),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Створено')
                            ->content(fn (?User $record): string =>
                                $record?->created_at?->format('d.m.Y H:i:s') ?? '-'
                            ),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Оновлено')
                            ->content(fn (?User $record): string =>
                                $record?->updated_at?->format('d.m.Y H:i:s') ?? '-'
                            ),
                    ])
                    ->columns(3)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ім\'я')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Роль')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Email підтверджений')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Роль')
                    ->options(UserRole::class),
            ])
            ->actions([
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
