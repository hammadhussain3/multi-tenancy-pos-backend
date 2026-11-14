<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantModule
{
    /**
     * Handle an incoming request and ensure tenant has access to the requested module.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');
            if (!$tenant->hasModule($module)) {
                return response()->json([
                    'success' => false,
                    'message' => "Access denied. The '{$module}' module is not enabled for your store plan.",
                    'error_code' => 'MODULE_NOT_ENABLED',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
