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
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_no')->index();
            $table->string('fbr_invoice_no')->nullable()->index();
            $table->string('device_id', 30)->default('REG-01');
            $table->string('cashier_name', 100)->default('Cashier');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('tax', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2);
            $table->string('payment_method', 30)->default('cash'); // cash, card, khata, split
            $table->decimal('cash_amount', 12, 2)->default(0.00);
            $table->decimal('card_amount', 12, 2)->default(0.00);
            $table->decimal('khata_amount', 12, 2)->default(0.00);
            $table->decimal('cash_tendered', 12, 2)->nullable();
            $table->decimal('change_given', 12, 2)->nullable();
            $table->boolean('is_synced')->default(true);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

