<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DayClosingController extends Controller
{
    public function __construct(
        protected InvoiceRepositoryInterface $invoiceRepo
    ) {}

    protected function getTenantId(Request $request): int
    {
        if ($request->user() && $request->user()->tenant_id) {
            return (int) $request->user()->tenant_id;
        }

        if (app()->bound('currentTenant') && app('currentTenant')) {
            return (int) app('currentTenant')->id;
        }

        return (int) $request->input('tenant_id', $request->query('tenant_id', 1));
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $date = $request->query('date', Carbon::today()->toDateString());

        $summary = $this->invoiceRepo->getDailySalesSummary($tenantId, $date);

        return response()->json([
            'success' => true,
            'date' => $date,
            'summary' => $summary,
        ]);
    }
}
