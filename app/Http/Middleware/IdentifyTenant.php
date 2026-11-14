<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request and identify the active tenant.
     *
     * Resolution hierarchy:
     * 1. X-Tenant-ID or X-Tenant-Subdomain HTTP headers (terminals / API clients)
     * 2. Host Subdomain (cloud SaaS deployments e.g. almadina.posify.pk)
     * 3. Authenticated user's tenant_id
     * 4. Local / Offline mode fallback (DEFAULT_TENANT_ID or first active tenant)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not identified or invalid tenant credentials.',
                'error_code' => 'TENANT_NOT_FOUND',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($tenant->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This store/tenant account is suspended or inactive.',
                'error_code' => 'TENANT_INACTIVE',
            ], Response::HTTP_FORBIDDEN);
        }

        // Subscription check: if expired, block mutating actions (POST/PUT/PATCH/DELETE) - preserve read-only mode
        if ($tenant->isExpired() && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Store subscription has expired. Read-only mode active. Please renew license.',
                'error_code' => 'SUBSCRIPTION_EXPIRED',
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        // Bind current tenant globally to the application service container
        app()->instance('currentTenant', $tenant);
        app()->instance(Tenant::class, $tenant);

        // Attach tenant to the request attributes for easy controller access
        $request->attributes->set('tenant', $tenant);

        $response = $next($request);

        // Include tenant trace in outgoing response header
        $response->headers->set('X-Tenant-UUID', $tenant->uuid);
        if ($tenant->subdomain) {
            $response->headers->set('X-Tenant-Subdomain', $tenant->subdomain);
        }

        return $response;
    }

    /**
     * Resolve tenant from request context.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Header: X-Tenant-ID (numeric id or UUID)
        $tenantIdHeader = $request->header('X-Tenant-ID');
        if (!empty($tenantIdHeader)) {
            if (is_numeric($tenantIdHeader)) {
                $tenant = Tenant::find((int) $tenantIdHeader);
                if ($tenant) return $tenant;
            } else {
                $tenant = Tenant::where('uuid', $tenantIdHeader)->first();
                if ($tenant) return $tenant;
            }
        }

        // 2. Header: X-Tenant-Subdomain
        $subdomainHeader = $request->header('X-Tenant-Subdomain');
        if (!empty($subdomainHeader)) {
            $tenant = Tenant::where('subdomain', strtolower(trim($subdomainHeader)))->first();
            if ($tenant) return $tenant;
        }

        // 3. Subdomain from Hostname (e.g. mart1.posify.com -> 'mart1')
        $host = $request->getHost();
        if ($this->isValidSubdomainHost($host)) {
            $parts = explode('.', $host);
            $subdomain = strtolower($parts[0]);
            $tenant = Tenant::where('subdomain', $subdomain)->first();
            if ($tenant) return $tenant;
        }

        // 4. Authenticated user's tenant
        if ($request->user() && $request->user()->tenant_id) {
            $tenant = Tenant::find($request->user()->tenant_id);
            if ($tenant) return $tenant;
        }

        // 5. Offline Local Fallback (env DEFAULT_TENANT_ID or first active tenant)
        $defaultTenantId = env('DEFAULT_TENANT_ID', 1);
        if ($defaultTenantId) {
            $tenant = Tenant::find($defaultTenantId);
            if ($tenant) return $tenant;
        }

        return Tenant::where('status', 'active')->first();
    }

    /**
     * Determine if host has an eligible subdomain (excludes IP addresses and localhost).
     */
    protected function isValidSubdomainHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.test')) {
            // Local dev testing e.g. almadina.test
            $parts = explode('.', $host);
            return count($parts) >= 2 && $parts[0] !== 'localhost';
        }

        $parts = explode('.', $host);
        return count($parts) >= 3;
    }
}
