<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('our_company_id')->nullable()->constrained('our_companies')->nullOnDelete();
            $table->boolean('with_vat')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount_total', 12, 2)->nullable();

            $table->index('our_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['our_company_id']);
            $table->dropIndex(['our_company_id']);
            $table->dropColumn('our_company_id');
            $table->dropColumn('with_vat');
            $table->dropColumn('subtotal');
            $table->dropColumn('discount_total');
        });
    }
};
