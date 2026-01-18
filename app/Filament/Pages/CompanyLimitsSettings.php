<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class CompanyLimitsSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.company-limits-settings';

    protected static ?string $navigationGroup = 'Налаштування';

    protected static ?string $navigationLabel = 'Ліміти компаній';

    protected static ?string $title = 'Налаштування лімітів компаній';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fop_max_amount' => Setting::get('limits.fop.max_amount', 1000000),
            'tov_max_amount' => Setting::get('limits.tov.max_amount', 2000000),
            'warning_threshold' => Setting::get('limits.warning_threshold', 90),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Глобальні ліміти компаній')
                    ->description('Налаштування максимальних сум оплачених рахунків за рік для різних типів компаній')
                    ->schema([
                        Forms\Components\TextInput::make('fop_max_amount')
                            ->label('Максимальна сума для ФОП (грн)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->suffix('грн')
                            ->helperText('Річний ліміт обороту для компаній типу ФОП (незалежно від системи оподаткування)'),

                        Forms\Components\TextInput::make('tov_max_amount')
                            ->label('Максимальна сума для ТОВ (грн)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->suffix('грн')
                            ->helperText('Річний ліміт обороту для компаній типу ТОВ (незалежно від системи оподаткування)'),

                        Forms\Components\TextInput::make('warning_threshold')
                            ->label('Поріг попередження (%)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->helperText('При досягненні цього відсотку від ліміту буде надіслано попередження'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Інформація')
                    ->description('Як працює система лімітів')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(new HtmlString(view('filament.pages.company-limits-settings-info')->render())),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('limits.fop.max_amount', $data['fop_max_amount'], 'decimal', 'limits');
        Setting::set('limits.tov.max_amount', $data['tov_max_amount'], 'decimal', 'limits');
        Setting::set('limits.warning_threshold', $data['warning_threshold'], 'integer', 'limits');

        Notification::make()
            ->success()
            ->title('Налаштування збережено')
            ->body('Глобальні ліміти компаній успішно оновлено')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Зберегти налаштування')
                ->submit('save'),
        ];
    }
}
