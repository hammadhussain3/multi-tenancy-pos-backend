<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('table_name', 50);
            $table->uuid('record_uuid')->index();
            $table->string('action', 20); // insert, update, delete
            $table->json('payload_json');
            $table->string('status', 20)->default('pending'); // pending, synced, failed
            $table->string('device_id', 30)->default('REG-01');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outbox');
    }
};

