<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Signed integer: positive for stock inward, negative for stock outward
            $table->integer('quantity');
            $table->string('type', 30); // sale, purchase, return, adjustment
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_uuid')->nullable()->index();
            $table->string('notes')->nullable();
            $table->string('device_id', 30)->default('REG-01');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

