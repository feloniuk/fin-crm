<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class CompanyLimitsSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Создаем настройки лимитов, если их еще нет
        $settings = [
            [
                'key' => 'limits.fop.max_amount',
                'value' => '1000000.00',
                'type' => 'decimal',
                'group' => 'limits',
                'description' => 'Максимальная годовая сумма оплаченных счетов для компаний типа ФОП',
            ],
            [
                'key' => 'limits.tov.max_amount',
                'value' => '2000000.00',
                'type' => 'decimal',
                'group' => 'limits',
                'description' => 'Максимальная годовая сумма оплаченных счетов для компаний типа ТОВ',
            ],
            [
                'key' => 'limits.warning_threshold',
                'value' => '90',
                'type' => 'integer',
                'group' => 'limits',
                'description' => 'Порог предупреждения о превышении лимита (в процентах)',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ Глобальные настройки лимитов созданы');
    }
}
