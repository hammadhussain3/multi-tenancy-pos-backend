<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Standard Login (Email & Password) for Store Managers and Owners.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::with('tenant')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password credentials.',
                'error_code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This user account is suspended.',
                'error_code' => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        $deviceName = $request->device_name ?? 'POS-Terminal';
        $token = $user->createToken($deviceName, ["role:{$user->role}"])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'subdomain' => $user->tenant->subdomain,
                'branch' => $user->tenant->branch,
                'currency' => $user->tenant->currency,
                'plan' => $user->tenant->plan,
            ] : null,
        ]);
    }

    /**
     * Fast Cashier PIN Login (4 to 6 Digits) for Supermarket Shift Changes.
     */
    public function pinLogin(Request $request): JsonResponse
    {
        $request->validate([
            'pin_code' => ['required', 'string', 'min:4', 'max:6'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'device_name' => ['nullable', 'string', 'max:50'],
        ]);

        $tenantId = $request->tenant_id;
        if (!$tenantId && app()->bound('currentTenant') && app('currentTenant')) {
            $tenantId = app('currentTenant')->id;
        }
        $tenantId = $tenantId ?? 1;

        // Search active users belonging to this tenant
        $users = User::with('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->get();

        $matchedUser = null;
        foreach ($users as $candidate) {
            if (Hash::check($request->pin_code, $candidate->pin_code)) {
                $matchedUser = $candidate;
                break;
            }
        }

        if (!$matchedUser) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect cashier PIN code. Please try again.',
                'error_code' => 'INVALID_PIN',
            ], 401);
        }

        $deviceName = $request->device_name ?? 'REG-01';
        $token = $matchedUser->createToken("{$deviceName}-CashierShift", ["role:{$matchedUser->role}"])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => "Welcome back, {$matchedUser->name}!",
            'token' => $token,
            'user' => [
                'id' => $matchedUser->id,
                'name' => $matchedUser->name,
                'email' => $matchedUser->email,
                'role' => $matchedUser->role,
                'tenant_id' => $matchedUser->tenant_id,
            ],
            'tenant' => $matchedUser->tenant ? [
                'id' => $matchedUser->tenant->id,
                'name' => $matchedUser->tenant->name,
                'subdomain' => $matchedUser->tenant->subdomain,
                'branch' => $matchedUser->tenant->branch,
                'currency' => $matchedUser->tenant->currency,
                'plan' => $matchedUser->tenant->plan,
            ] : null,
        ]);
    }

    /**
     * Get Current Authenticated Cashier / Manager profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
            'tenant' => $user->tenant,
        ]);
    }

    /**
     * Revoke Current Active Token (Shift Logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }
}
