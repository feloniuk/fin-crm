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
            $table->string('delivery_name')->nullable()->after('customer_comment');
            $table->string('delivery_address')->nullable()->after('delivery_name');
            $table->string('delivery_city')->nullable()->after('delivery_address');
            $table->string('delivery_type')->nullable()->after('delivery_city');
            $table->string('payment_type')->nullable()->after('delivery_type');
            $table->boolean('payed')->default(false)->after('payment_type');
            $table->text('manager_comment')->nullable()->after('payed');
            $table->string('currency', 3)->default('UAH')->after('manager_comment');
            $table->tinyInteger('api_status')->nullable()->comment('API stat_status field')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_name',
                'delivery_address',
                'delivery_city',
                'delivery_type',
                'payment_type',
                'payed',
                'manager_comment',
                'currency',
                'api_status',
            ]);
        });
    }
};
