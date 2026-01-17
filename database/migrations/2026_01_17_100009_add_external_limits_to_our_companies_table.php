<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('our_companies', function (Blueprint $table) {
            // Новые поля для учета внешних продаж и override
            $table->decimal('external_sales_amount', 15, 2)
                ->default(0)
                ->after('annual_limit')
                ->comment('Сумма продаж вне системы для учета в лимите');

            $table->decimal('remaining_limit_override', 15, 2)
                ->nullable()
                ->after('external_sales_amount')
                ->comment('Ручная установка остатка лимита (переопределяет расчет)');

            // Индексы для производительности
            $table->index('external_sales_amount');
        });
    }

    public function down(): void
    {
        Schema::table('our_companies', function (Blueprint $table) {
            $table->dropIndex(['external_sales_amount']);
            $table->dropColumn([
                'external_sales_amount',
                'remaining_limit_override',
            ]);
        });
    }
};
