<?php

namespace App\Notifications;

use App\Models\OurCompany;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as BaseNotification;

class LimitWarningNotification extends BaseNotification
{
    use Queueable;

    public function __construct(
        public readonly OurCompany $company,
        public readonly float $percent,
        public readonly float $remaining,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => '⚠️ Попередження: Наближено до ліміту',
            'message' => "{$this->company->name}: Використано {$this->percent}% ліміту. Залишок: " .
                number_format($this->remaining, 2, ',', ' ') . ' грн',
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
        ];
    }

    public static function sendToDashboard(OurCompany $company, float $percent, float $remaining)
    {
        Notification::make()
            ->warning()
            ->title('⚠️ Попередження: Наближено до ліміту')
            ->body("{$company->name}: Використано {$percent}% ліміту. Залишок: " .
                number_format($remaining, 2, ',', ' ') . ' грн')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Переглянути')
                    ->url('/admin/our-companies/' . $company->id . '/edit'),
            ])
            ->send();
    }
}
