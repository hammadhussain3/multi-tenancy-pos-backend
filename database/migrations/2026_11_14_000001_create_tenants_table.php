<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('branch');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('ntn')->nullable();
            $table->string('strn')->nullable();
            $table->string('fbr_pos_id')->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

