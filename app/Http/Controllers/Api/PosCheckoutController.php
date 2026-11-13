<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\InvoiceResource;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Services\Pos\CheckoutService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosCheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected InvoiceRepositoryInterface $invoiceRepo
    ) {}

    /**
     * Handle POS checkout request.
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $invoice = $this->checkoutService->execute(
                tenantId: (int) $request->validated('tenant_id'),
                items: $request->validated('items'),
                paymentMethod: $request->validated('payment_method'),
                tenderBreakdown: $request->validated('tender_breakdown'),
                customerId: $request->validated('customer_id'),
                orderDiscount: (float) $request->validated('discount', 0.00),
                deviceId: $request->validated('device_id', 'REG-01'),
                cashierName: $request->validated('cashier_name', 'Cashier')
            );

            return response()->json([
                'success' => true,
                'message' => 'Checkout processed successfully.',
                'data' => new InvoiceResource($invoice),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List completed invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 1);
        $search = $request->query('search');

        $invoices = $this->invoiceRepo->getAllByTenant($tenantId, $search);

        return response()->json([
            'success' => true,
            'data' => InvoiceResource::collection($invoices),
        ]);
    }
}

