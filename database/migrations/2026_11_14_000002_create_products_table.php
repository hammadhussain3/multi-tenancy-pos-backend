<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('barcode')->index();
            $table->string('sku')->index();
            $table->string('name');
            $table->string('name_urdu')->nullable();
            $table->string('category')->index();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('purchase_price', 12, 2)->default(0.00);
            $table->decimal('retail_price', 12, 2)->default(0.00);
            $table->integer('min_stock_alert')->default(5);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'barcode']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

