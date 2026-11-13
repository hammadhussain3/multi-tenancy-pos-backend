<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->dateTime('entry_date');
            $table->string('reference_type', 40); // sale, payment_received, return, adjustment
            $table->string('reference_no', 60);
            $table->uuid('reference_uuid')->nullable()->index();
            $table->decimal('debit', 12, 2)->default(0.00); // Increases Udhaar receivable
            $table->decimal('credit', 12, 2)->default(0.00); // Decreases Udhaar receivable
            $table->decimal('running_balance', 12, 2)->default(0.00);
            $table->string('narration')->nullable();
            $table->string('device_id', 30)->default('REG-01');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};

