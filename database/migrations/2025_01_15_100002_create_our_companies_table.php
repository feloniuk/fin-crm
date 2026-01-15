<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('our_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // CompanyType enum: fop, tov
            $table->string('tax_system'); // TaxSystem enum: single_tax, vat
            $table->string('edrpou_ipn', 10)->unique();
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('bank_details')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_position')->nullable();
            $table->decimal('annual_limit', 15, 2)->nullable(); // Only for single_tax
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('type');
            $table->index('tax_system');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('our_companies');
    }
};
