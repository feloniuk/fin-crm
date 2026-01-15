<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('our_company_id')->constrained('our_companies')->restrictOnDelete();
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->boolean('with_vat')->default(false);
            $table->text('comment')->nullable();
            $table->string('discount_type')->default('none'); // DiscountType enum
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2); // Sum before VAT
            $table->decimal('vat_amount', 12, 2)->default(0); // VAT 20%
            $table->decimal('total', 12, 2); // Final amount
            $table->boolean('is_paid')->default(false);
            $table->date('paid_at')->nullable();
            $table->string('excel_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index('invoice_date');
            $table->index('our_company_id');
            $table->index('counterparty_id');
            $table->index('is_paid');
            $table->index(['our_company_id', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
