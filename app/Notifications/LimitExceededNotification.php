<?php

namespace App\Notifications;

use App\Models\OurCompany;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification as BaseNotification;

class LimitExceededNotification extends BaseNotification
{
    use Queueable;

    public function __construct(
        public readonly OurCompany $company,
        public readonly float $exceeded,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => '❌ КРИТИЧНО: Ліміт перевищено!',
            'message' => "{$this->company->name}: Ліміт перевищено на " .
                number_format($this->exceeded, 2, ',', ' ') . ' грн',
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'exceeded' => $this->exceeded,
        ];
    }

    public static function sendToDashboard(OurCompany $company, float $exceeded)
    {
        Notification::make()
            ->danger()
            ->title('❌ КРИТИЧНО: Ліміт перевищено!')
            ->body("{$company->name}: Ліміт перевищено на " .
                number_format($exceeded, 2, ',', ' ') . ' грн')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('Переглянути')
                    ->url('/admin/our-companies/' . $company->id . '/edit'),
            ])
            ->send();
    }
}
