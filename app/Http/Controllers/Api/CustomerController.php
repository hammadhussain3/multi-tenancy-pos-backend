<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordKhataPaymentRequest;
use App\Http\Resources\CustomerResource;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Pos\KhataService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepo,
        protected KhataService $khataService
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

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $search = $request->query('search');

        $customers = $this->customerRepo->getAllByTenant($tenantId, $search);

        return response()->json([
            'success' => true,
            'data' => CustomerResource::collection($customers),
        ]);
    }

    public function ledger(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $customer = $this->customerRepo->findById($tenantId, $id);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $ledgerEntries = $this->customerRepo->getLedgerHistory($tenantId, $id);

        return response()->json([
            'success' => true,
            'customer' => new CustomerResource($customer),
            'ledger' => $ledgerEntries,
        ]);
    }

    public function recordPayment(RecordKhataPaymentRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->validated('tenant_id', $this->getTenantId($request));
        $customer = $this->customerRepo->findById($tenantId, $id);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $receiptNo = $request->validated('receipt_no') ?? ('REC-' . Carbon::now()->timestamp);
        $narration = $request->validated('narration') ?? 'Cash Received against outstanding Udhaar';

        $entry = $this->khataService->receivePayment(
            tenantId: $tenantId,
            customerId: $id,
            amount: (float) $request->validated('amount'),
            receiptNo: $receiptNo,
            narration: $narration
        );

        $fresh = $this->customerRepo->findById($tenantId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Khata payment recorded successfully.',
            'entry' => $entry,
            'customer' => new CustomerResource($fresh),
        ]);
    }
}
