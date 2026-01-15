<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('edrpou_ipn', 10)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_auto_created')->default(false);
            $table->timestamps();

            $table->index('edrpou_ipn');
            $table->index('is_auto_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
