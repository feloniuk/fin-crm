<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('customer_name');
            $table->string('customer_phone', 20)->nullable();
            $table->text('customer_comment')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('new'); // OrderStatus enum
            $table->json('raw_data')->nullable(); // Full API response
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'external_id']);
            $table->index('status');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
