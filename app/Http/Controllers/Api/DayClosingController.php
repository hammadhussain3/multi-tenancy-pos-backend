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

    public function summary(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 1);
        $date = $request->query('date', Carbon::today()->toDateString());

        $summary = $this->invoiceRepo->getDailySalesSummary($tenantId, $date);

        return response()->json([
            'success' => true,
            'date' => $date,
            'summary' => $summary,
        ]);
    }
}

