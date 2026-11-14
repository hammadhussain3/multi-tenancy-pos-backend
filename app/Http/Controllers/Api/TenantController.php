<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Get the active identified tenant for this session.
     */
    public function current(Request $request): JsonResponse
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant identified.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'branch' => $tenant->branch,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'currency' => $tenant->currency,
                'status' => $tenant->status,
                'plan' => $tenant->plan,
                'modules' => $tenant->modules,
                'expires_at' => $tenant->expires_at?->format('Y-m-d'),
                'is_expired' => $tenant->isExpired(),
            ],
        ]);
    }

    /**
     * List all available stores/tenants (for multi-store switcher in POS header).
     */
    public function index(): JsonResponse
    {
        $tenants = Tenant::where('status', 'active')
            ->select(['id', 'uuid', 'name', 'subdomain', 'branch', 'currency', 'plan'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }
}
