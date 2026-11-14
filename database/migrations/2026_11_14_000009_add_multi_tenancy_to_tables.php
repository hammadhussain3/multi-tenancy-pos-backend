<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subdomain')->nullable()->unique()->after('name');
            $table->string('plan', 50)->default('basic')->after('currency');
            $table->json('modules')->nullable()->after('plan');
            $table->date('expires_at')->nullable()->after('modules');
        });

        // Seed default multi-tenant settings for existing tenant (id = 1)
        DB::table('tenants')->where('id', 1)->update([
            'subdomain' => 'almadina',
            'plan' => 'pro',
            'modules' => json_encode(['pos', 'inventory', 'khata', 'reports']),
            'expires_at' => '2030-12-31',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['tenant_id', 'invoice_no'], 'invoices_tenant_invoice_no_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_invoice_no_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_tenant_barcode_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subdomain', 'plan', 'modules', 'expires_at']);
        });
    }
};
