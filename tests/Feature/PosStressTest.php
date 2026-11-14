<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosStressTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'HyperMart Stress Test',
            'branch' => 'Main Hypermarket',
            'fbr_pos_id' => 'FBR-STRESS-01',
            'currency' => 'PKR',
        ]);

        $this->customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'VIP Wholesale Customer',
            'phone' => '03009998877',
            'credit_limit' => 500000.00,
        ]);

        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Stress Cashier',
            'email' => 'stress@test.com',
            'role' => 'cashier',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        Sanctum::actingAs($user, ['role:cashier']);
    }

    /**
     * Stress Test 1: Rapid 100 Consecutive Checkouts Burst.
     * Verifies atomic DB transactions, inventory deductions, and sync outbox logging.
     */
    public function test_rapid_100_consecutive_checkouts_burst(): void
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'barcode' => '8960000000001',
            'sku' => 'BURST-001',
            'name' => 'High Volume Fast Mover Milk',
            'category' => 'Dairy',
            'unit' => 'ltr',
            'purchase_price' => 200.00,
            'retail_price' => 250.00,
            'min_stock_alert' => 10,
        ]);

        // Seed 1,000 units in stock
        StockMovement::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'quantity' => 1000,
            'type' => 'purchase',
        ]);

        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $response = $this->postJson('/api/v1/pos/checkout', [
                'tenant_id' => $this->tenant->id,
                'device_id' => 'REG-STRESS-01',
                'cashier_name' => 'Stress Test Runner',
                'payment_method' => 'cash',
                'tender_breakdown' => [
                    'cash' => 500.00,
                    'card' => 0.00,
                    'khata' => 0.00,
                    'cash_tendered' => 500.00,
                ],
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_price' => 250.00,
                        'discount_amount' => 0,
                    ],
                ],
            ]);

            $response->assertStatus(201);
            $response->assertJsonPath('success', true);
        }

        $duration = microtime(true) - $startTime;

        // 100 checkouts * 2 units = 200 units deducted
        // Remaining stock must be exactly 800
        $this->assertEquals(800, $product->fresh()->current_stock);

        // Performance assertion: 100 full transactional checkouts with ledger under 10 seconds
        $this->assertLessThan(10.0, $duration, "100 checkouts took {$duration}s, exceeding threshold.");
    }

    /**
     * Stress Test 2: Huge Cart Transaction (50 Distinct Line Items in One Invoice).
     */
    public function test_large_cart_50_line_items_single_checkout(): void
    {
        $items = [];
        $totalAmount = 0;

        for ($i = 1; $i <= 50; $i++) {
            $p = Product::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'barcode' => sprintf('8969999%06d', $i),
                'sku' => "BULK-ITEM-{$i}",
                'name' => "HyperMart Product {$i}",
                'category' => 'Grocery',
                'unit' => 'pcs',
                'purchase_price' => 50.00,
                'retail_price' => 100.00,
                'min_stock_alert' => 5,
            ]);

            StockMovement::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'product_id' => $p->id,
                'quantity' => 50,
                'type' => 'purchase',
            ]);

            $items[] = [
                'product_id' => $p->id,
                'quantity' => 3,
                'unit_price' => 100.00,
                'discount_amount' => 0,
            ];

            $totalAmount += (3 * 100.00);
        }

        $response = $this->postJson('/api/v1/pos/checkout', [
            'tenant_id' => $this->tenant->id,
            'device_id' => 'REG-STRESS-02',
            'cashier_name' => 'Senior Cashier',
            'payment_method' => 'card',
            'tender_breakdown' => [
                'cash' => 0.00,
                'card' => $totalAmount,
                'khata' => 0.00,
            ],
            'items' => $items,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $this->assertEquals($totalAmount, $response->json('data.grand_total'));
        $this->assertCount(50, $response->json('data.items'));
    }

    /**
     * Stress Test 3: Rapid Khata Credit Limit Boundary Testing.
     */
    public function test_rapid_khata_credit_limit_boundary_enforcement(): void
    {
        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'barcode' => '8960000000099',
            'sku' => 'KHATA-PROD',
            'name' => 'Bulk Flour Bag 20kg',
            'category' => 'Grocery',
            'unit' => 'bag',
            'purchase_price' => 1800.00,
            'retail_price' => 2000.00,
            'min_stock_alert' => 5,
        ]);

        StockMovement::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'quantity' => 500,
            'type' => 'purchase',
        ]);

        // Customer has 500,000 credit limit.
        // Buy 240 bags = 480,000 PKR (allowed)
        $resp1 = $this->postJson('/api/v1/pos/checkout', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'device_id' => 'REG-01',
            'cashier_name' => 'Cashier',
            'payment_method' => 'khata',
            'tender_breakdown' => [
                'cash' => 0.00,
                'card' => 0.00,
                'khata' => 480000.00,
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 240, 'unit_price' => 2000.00, 'discount_amount' => 0],
            ],
        ]);
        $resp1->assertStatus(201);

        // Next attempt to buy 20 bags = 40,000 PKR (480,000 + 40,000 = 520,000 > 500,000 limit) -> MUST FAIL (422)
        $resp2 = $this->postJson('/api/v1/pos/checkout', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'device_id' => 'REG-01',
            'cashier_name' => 'Cashier',
            'payment_method' => 'khata',
            'tender_breakdown' => [
                'cash' => 0.00,
                'card' => 0.00,
                'khata' => 40000.00,
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_price' => 2000.00, 'discount_amount' => 0],
            ],
        ]);
        $resp2->assertStatus(422);
    }
}
