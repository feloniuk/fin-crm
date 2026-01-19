<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Перейменовуємо discount на discount_value
            $table->renameColumn('discount', 'discount_value');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Додаємо тип знижки (percent або fixed)
            $table->string('discount_type')->nullable()->after('unit_price');
            // Додаємо обчислену суму знижки
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
            // Додаємо subtotal (сума без знижки)
            $table->decimal('subtotal', 12, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_amount', 'subtotal']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('discount_value', 'discount');
        });
    }
};
