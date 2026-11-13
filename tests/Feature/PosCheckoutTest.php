<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Al-Madina Test Mart',
            'branch' => 'Lahore Branch',
            'fbr_pos_id' => 'POS-TEST-001',
            'currency' => 'PKR',
        ]);

        $this->product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'barcode' => '8964000111111',
            'sku' => 'TEST-PROD-01',
            'name' => 'Tapal Tea 450g',
            'category' => 'Beverages',
            'unit' => 'pack',
            'purchase_price' => 600.00,
            'retail_price' => 700.00,
            'min_stock_alert' => 5,
        ]);

        // Add 20 units to stock via append-only ledger
        StockMovement::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'type' => 'purchase',
        ]);

        $this->customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'phone' => '03001234567',
            'credit_limit' => 10000.00,
        ]);
    }

    public function test_can_list_products_with_calculated_stock(): void
    {
        $response = $this->getJson("/api/v1/pos/products?tenant_id={$this->tenant->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.current_stock', 20)
            ->assertJsonPath('data.0.retail_price', 700);
    }

    public function test_can_perform_successful_cash_checkout(): void
    {
        $payload = [
            'tenant_id' => $this->tenant->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 700.00,
                    'discount_amount' => 0.00,
                ],
            ],
            'payment_method' => 'cash',
            'tender_breakdown' => [
                'cash' => 1400.00,
                'card' => 0.00,
                'khata' => 0.00,
                'cash_tendered' => 1500.00,
            ],
            'discount' => 0.00,
        ];

        $response = $this->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.grand_total', 1400)
            ->assertJsonPath('data.change_given', 100);

        // Verify stock deducted in append-only stock_movements table
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity' => -2,
            'type' => 'sale',
        ]);

        // Verify total calculated stock is now 18
        $this->assertEquals(18, $this->product->fresh()->currentStock);

        // Verify invoice was recorded in sync outbox for cloud sync
        $this->assertDatabaseHas('sync_outbox', [
            'tenant_id' => $this->tenant->id,
            'table_name' => 'invoices',
            'status' => 'pending',
        ]);
    }

    public function test_can_perform_split_khata_checkout_and_updates_ledger(): void
    {
        $payload = [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'unit_price' => 700.00,
                ],
            ],
            'payment_method' => 'split',
            'tender_breakdown' => [
                'cash' => 1000.00,
                'card' => 0.00,
                'khata' => 1100.00, // 2100 total
            ],
        ];

        $response = $this->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.grand_total', 2100);

        // Verify Khata double-entry debit recorded
        $this->assertDatabaseHas('customer_ledger_entries', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'reference_type' => 'sale',
            'debit' => 1100.00,
            'credit' => 0.00,
            'running_balance' => 1100.00,
        ]);

        $this->assertEquals(1100.00, $this->customer->fresh()->currentBalance);
    }

    public function test_checkout_fails_when_insufficient_stock(): void
    {
        $payload = [
            'tenant_id' => $this->tenant->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 999, // Only 20 available
                    'unit_price' => 700.00,
                ],
            ],
            'payment_method' => 'cash',
            'tender_breakdown' => [
                'cash' => 999 * 700,
            ],
        ];

        $response = $this->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        // Verify zero stock changes
        $this->assertEquals(20, $this->product->fresh()->currentStock);
    }

    public function test_can_record_customer_payment_against_khata(): void
    {
        // Give customer initial 5000 Udhaar
        $this->customer->ledgerEntries()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'entry_date' => now(),
            'reference_type' => 'sale',
            'reference_no' => 'INV-TEST-001',
            'debit' => 5000.00,
            'credit' => 0.00,
            'running_balance' => 5000.00,
        ]);

        $response = $this->postJson("/api/v1/pos/customers/{$this->customer->id}/payment", [
            'tenant_id' => $this->tenant->id,
            'amount' => 2000.00,
            'narration' => 'Test payment received',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.current_balance', 3000);

        $this->assertEquals(3000.00, $this->customer->fresh()->currentBalance);
    }
}

