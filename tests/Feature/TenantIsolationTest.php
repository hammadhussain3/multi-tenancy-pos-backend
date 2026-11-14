<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Al-Madina Supermart',
            'subdomain' => 'almadina',
            'branch' => 'Lahore Gulberg',
            'plan' => 'pro',
            'modules' => ['pos', 'inventory', 'khata', 'reports'],
            'expires_at' => now()->addYear(),
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Bismillah Cash & Carry',
            'subdomain' => 'bismillah',
            'branch' => 'Karachi Clifton',
            'plan' => 'basic',
            'modules' => ['pos', 'inventory'],
            'expires_at' => now()->addYear(),
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cashier A',
            'email' => 'cashierA@test.com',
            'role' => 'cashier',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cashier B',
            'email' => 'cashierB@test.com',
            'role' => 'cashier',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Eloquent global scope isolates products between tenants.
     */
    public function test_tenant_a_cannot_see_tenant_b_products(): void
    {
        // Bind Tenant A and create 3 products
        app()->instance('currentTenant', $this->tenantA);

        Product::create([
            'name' => 'Tenant A Product 1',
            'barcode' => 'BAR-A-001',
            'sku' => 'SKU-A-001',
            'category' => 'Grocery',
            'unit' => 'pcs',
            'retail_price' => 100.00,
        ]);
        Product::create([
            'name' => 'Tenant A Product 2',
            'barcode' => 'BAR-A-002',
            'sku' => 'SKU-A-002',
            'category' => 'Grocery',
            'unit' => 'pcs',
            'retail_price' => 200.00,
        ]);

        $this->assertCount(2, Product::all());

        // Switch to Tenant B and create 1 product
        app()->instance('currentTenant', $this->tenantB);
        Product::create([
            'name' => 'Tenant B Product 1',
            'barcode' => 'BAR-B-001',
            'sku' => 'SKU-B-001',
            'category' => 'Beverages',
            'unit' => 'pack',
            'retail_price' => 300.00,
        ]);

        // Tenant B must only see 1 product, completely hidden from Tenant A
        $tenantBProducts = Product::all();
        $this->assertCount(1, $tenantBProducts);
        $this->assertEquals('Tenant B Product 1', $tenantBProducts->first()->name);

        // Switch back to Tenant A, must still only see 2 products
        app()->instance('currentTenant', $this->tenantA);
        $tenantAProducts = Product::all();
        $this->assertCount(2, $tenantAProducts);
        $this->assertFalse($tenantAProducts->contains('name', 'Tenant B Product 1'));
    }

    /**
     * Test 2: API routes isolate products via X-Tenant-ID header.
     */
    public function test_multi_tenant_products_api_isolation_via_headers(): void
    {
        app()->instance('currentTenant', $this->tenantA);
        Product::create([
            'name' => 'Tapal Tea (Tenant A)',
            'barcode' => '896400010001',
            'sku' => 'TEA-01',
            'category' => 'Beverages',
            'unit' => 'pcs',
            'retail_price' => 500.00,
        ]);

        app()->instance('currentTenant', $this->tenantB);
        Product::create([
            'name' => 'Lipton Tea (Tenant B)',
            'barcode' => '896400020002',
            'sku' => 'TEA-02',
            'category' => 'Beverages',
            'unit' => 'pcs',
            'retail_price' => 550.00,
        ]);

        $tokenA = $this->userA->createToken('A')->plainTextToken;
        $tokenB = $this->userB->createToken('B')->plainTextToken;

        // Query API as Tenant A
        $responseA = $this->withHeaders([
            'X-Tenant-ID' => (string) $this->tenantA->id,
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/api/v1/pos/products');

        $responseA->assertOk();
        $responseA->assertJsonCount(1, 'data');
        $responseA->assertJsonPath('data.0.name', 'Tapal Tea (Tenant A)');

        // Clear guard cache for the next request in the same test method
        auth()->forgetGuards();

        // Query API as Tenant B
        $responseB = $this->withHeaders([
            'X-Tenant-ID' => (string) $this->tenantB->id,
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/pos/products');

        $responseB->assertOk();
        $responseB->assertJsonCount(1, 'data');
        $responseB->assertJsonPath('data.0.name', 'Lipton Tea (Tenant B)');
    }

    /**
     * Test 3: Same manufacturer barcode can exist in both tenants independently (composite unique index).
     */
    public function test_barcode_collision_allowed_across_different_tenants(): void
    {
        $sharedBarcode = '8964000999999';

        // Tenant A stocks Nestle Water 1.5L
        app()->instance('currentTenant', $this->tenantA);
        $productA = Product::create([
            'name' => 'Nestle Water 1.5L (Tenant A)',
            'barcode' => $sharedBarcode,
            'sku' => 'WAT-01',
            'category' => 'Beverages',
            'unit' => 'bottle',
            'purchase_price' => 80.00,
            'retail_price' => 110.00,
        ]);

        // Tenant B stocks the exact same Nestle Water 1.5L with their own retail price
        app()->instance('currentTenant', $this->tenantB);
        $productB = Product::create([
            'name' => 'Nestle Water 1.5L (Tenant B)',
            'barcode' => $sharedBarcode,
            'sku' => 'WAT-01',
            'category' => 'Beverages',
            'unit' => 'bottle',
            'purchase_price' => 85.00,
            'retail_price' => 120.00,
        ]);

        $this->assertEquals($sharedBarcode, $productA->barcode);
        $this->assertEquals($sharedBarcode, $productB->barcode);
        $this->assertNotEquals($productA->tenant_id, $productB->tenant_id);

        // However, within the SAME tenant, duplicate barcode must be rejected
        $this->expectException(QueryException::class);
        Product::create([
            'name' => 'Duplicate Nestle Water in Tenant B',
            'barcode' => $sharedBarcode,
            'sku' => 'WAT-02',
            'category' => 'Beverages',
            'unit' => 'bottle',
            'retail_price' => 120.00,
        ]);
    }

    /**
     * Test 4: Customer and Khata ledger isolation across tenants.
     */
    public function test_customer_and_khata_ledger_isolation(): void
    {
        app()->instance('currentTenant', $this->tenantA);
        $custA = Customer::create([
            'name' => 'Customer of Tenant A',
            'phone' => '0300-0000001',
            'credit_limit' => 50000.00,
        ]);

        app()->instance('currentTenant', $this->tenantB);
        $custB = Customer::create([
            'name' => 'Customer of Tenant B',
            'phone' => '0300-0000002',
            'credit_limit' => 20000.00,
        ]);

        // Tenant A must only see Customer A
        app()->instance('currentTenant', $this->tenantA);
        $this->assertCount(1, Customer::all());
        $this->assertEquals($custA->id, Customer::first()->id);

        // Tenant B must only see Customer B
        app()->instance('currentTenant', $this->tenantB);
        $this->assertCount(1, Customer::all());
        $this->assertEquals($custB->id, Customer::first()->id);
    }

    /**
     * Test 5: Tenant A cannot checkout using Tenant B's product.
     */
    public function test_tenant_a_cannot_checkout_tenant_b_product(): void
    {
        // Create product in Tenant B with 10 stock
        app()->instance('currentTenant', $this->tenantB);
        $productB = Product::create([
            'name' => 'Exclusive Item in Store B',
            'barcode' => '8964000555555',
            'sku' => 'EXC-01',
            'category' => 'Snacks',
            'unit' => 'pack',
            'retail_price' => 150.00,
        ]);
        StockMovement::create([
            'product_id' => $productB->id,
            'quantity' => 10,
            'type' => 'purchase',
        ]);

        // Attempt checkout on Tenant A terminal using Product B's ID
        $tokenA = $this->userA->createToken('A')->plainTextToken;
        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $this->tenantA->id,
            'Authorization' => "Bearer {$tokenA}",
        ])->postJson('/api/v1/pos/checkout', [
                'tenant_id' => $this->tenantA->id,
                'items' => [
                    [
                        'product_id' => $productB->id,
                        'quantity' => 1,
                        'unit_price' => 150.00,
                    ],
                ],
                'payment_method' => 'cash',
                'tender_breakdown' => [
                    'cash' => 150.00,
                    'cash_tendered' => 200.00,
                ],
            ]);

        // Must reject because Product B does not belong to Tenant A
        $response->assertStatus(422);
    }

    /**
     * Test 6: Tenant resolution via X-Tenant-Subdomain header.
     */
    public function test_tenant_resolution_via_subdomain_header(): void
    {
        $tokenB = $this->userB->createToken('B')->plainTextToken;
        $response = $this->withHeaders([
            'X-Tenant-Subdomain' => 'bismillah',
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/pos/tenant/current');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Bismillah Cash & Carry');
        $response->assertJsonPath('data.subdomain', 'bismillah');
    }

    /**
     * Test 7: Expired tenant enters read-only mode (GET allowed, POST blocked with 402).
     */
    public function test_expired_tenant_read_only_mode(): void
    {
        $expiredTenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Expired Mart',
            'subdomain' => 'expiredmart',
            'branch' => 'Old Branch',
            'plan' => 'basic',
            'expires_at' => now()->subDay(), // Expired yesterday
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $expiredUser = User::create([
            'tenant_id' => $expiredTenant->id,
            'name' => 'Expired Cashier',
            'email' => 'expired@test.com',
            'role' => 'cashier',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $expiredToken = $expiredUser->createToken('Expired')->plainTextToken;

        // GET request is allowed in read-only mode
        $getRes = $this->withHeaders([
            'X-Tenant-ID' => (string) $expiredTenant->id,
            'Authorization' => "Bearer {$expiredToken}",
        ])->getJson('/api/v1/pos/products');
        $getRes->assertOk();

        // POST request is blocked with 402 Payment Required
        $postRes = $this->withHeaders([
            'X-Tenant-ID' => (string) $expiredTenant->id,
            'Authorization' => "Bearer {$expiredToken}",
        ])->postJson('/api/v1/pos/products', [
                'name' => 'New Product',
                'barcode' => '8964000991122',
                'category' => 'Grocery',
                'unit' => 'pcs',
                'purchase_price' => 10.00,
                'retail_price' => 20.00,
            ]);
        $postRes->assertStatus(402);
        $postRes->assertJsonPath('error_code', 'SUBSCRIPTION_EXPIRED');
    }
}
