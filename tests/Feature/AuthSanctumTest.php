<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthSanctumTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $manager;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Al-Madina Hypermart',
            'subdomain' => 'almadina',
            'branch' => 'Lahore Branch',
            'plan' => 'pro',
            'modules' => ['pos', 'inventory', 'khata'],
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hammad Hussain',
            'email' => 'admin@posify.pk',
            'role' => 'admin',
            'password' => Hash::make('secret123'),
            'pin_code' => Hash::make('1234'),
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bilal Ahmed',
            'email' => 'bilal@posify.pk',
            'role' => 'cashier',
            'password' => Hash::make('secret123'),
            'pin_code' => Hash::make('7788'),
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: Manager can login via Email and Password.
     */
    public function test_manager_can_login_with_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@posify.pk',
            'password' => 'secret123',
            'device_name' => 'Admin-Laptop',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.email', 'admin@posify.pk');
        $response->assertJsonPath('user.role', 'admin');
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test 2: Invalid password login is rejected.
     */
    public function test_invalid_password_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@posify.pk',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    }

    /**
     * Test 3: Cashier can perform fast PIN login at terminal.
     */
    public function test_cashier_can_login_with_pin(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/pin-login', [
            'tenant_id' => $this->tenant->id,
            'pin_code' => '7788',
            'device_name' => 'REG-01',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.name', 'Bilal Ahmed');
        $response->assertJsonPath('user.role', 'cashier');
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test 4: Incorrect cashier PIN is rejected.
     */
    public function test_incorrect_pin_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/pos/auth/pin-login', [
            'tenant_id' => $this->tenant->id,
            'pin_code' => '0000',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_PIN');
    }

    /**
     * Test 5: Authenticated user can fetch their own profile via /me.
     */
    public function test_authenticated_user_can_access_me_endpoint(): void
    {
        $token = $this->manager->createToken('TestDevice')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/pos/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.email', 'admin@posify.pk');
        $response->assertJsonPath('user.role', 'admin');
    }

    /**
     * Test 6: Cashier can logout and token is revoked.
     */
    public function test_user_can_logout_and_revoke_token(): void
    {
        $token = $this->cashier->createToken('CashierShift')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/pos/auth/logout');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify token row was actually deleted in database
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Clear test runner auth guard cache
        $this->app['auth']->forgetGuards();

        // Subsequent call with revoked token should fail with 401 Unauthenticated
        $subsequent = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/pos/auth/me');

        $subsequent->assertStatus(401);
    }

    /**
     * Test 7: Unauthenticated access to products is blocked by backend.
     */
    public function test_unauthenticated_request_to_products_is_blocked_with_401(): void
    {
        $response = $this->getJson('/api/v1/pos/products');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    /**
     * Test 8: Unauthenticated access to customers and khata is blocked by backend.
     */
    public function test_unauthenticated_request_to_customers_is_blocked_with_401(): void
    {
        $response = $this->getJson('/api/v1/pos/customers');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    /**
     * Test 9: Unauthenticated checkout is blocked by backend.
     */
    public function test_unauthenticated_checkout_is_blocked_with_401(): void
    {
        $response = $this->postJson('/api/v1/pos/checkout', [
            'tenant_id' => $this->tenant->id,
            'items' => [],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    /**
     * Test 10: Authenticated user with valid token can access products.
     */
    public function test_authenticated_user_with_token_can_access_products(): void
    {
        $token = $this->cashier->createToken('CashierShift')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/pos/products');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }
}
