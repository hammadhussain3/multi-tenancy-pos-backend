<?php

namespace App\Services\Pos;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SyncOutboxRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        protected InvoiceRepositoryInterface $invoiceRepo,
        protected ProductRepositoryInterface $productRepo,
        protected CustomerRepositoryInterface $customerRepo,
        protected InventoryService $inventoryService,
        protected KhataService $khataService,
        protected SyncOutboxRepositoryInterface $syncOutboxRepo
    ) {}

    /**
     * Executes atomic POS checkout with append-only ledger and outbox persistence.
     *
     * @throws Exception
     */
    public function execute(
        int $tenantId,
        array $items,
        string $paymentMethod,
        array $tenderBreakdown,
        ?int $customerId = null,
        ?float $orderDiscount = 0.00,
        string $deviceId = 'REG-01',
        string $cashierName = 'Hammad Hussain'
    ): Invoice {
        return DB::transaction(function () use (
            $tenantId,
            $items,
            $paymentMethod,
            $tenderBreakdown,
            $customerId,
            $orderDiscount,
            $deviceId,
            $cashierName
        ) {
            if (empty($items)) {
                throw new Exception('Cannot checkout an empty cart.');
            }

            $tenant = Tenant::findOrFail($tenantId);

            // 1. Verify stock availability and prepare line item totals
            $subtotal = 0.00;
            $itemsPayload = [];

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $lineDiscount = (float) ($item['discount_amount'] ?? 0.00);

                $product = $this->productRepo->findById($tenantId, $productId);
                if (!$product) {
                    throw new Exception("Product ID {$productId} not found for this tenant.");
                }

                $availableStock = $this->inventoryService->getStock($tenantId, $productId);
                if ($availableStock < $quantity) {
                    throw new Exception("Insufficient stock for '{$product->name}'. Available: {$availableStock}, Requested: {$quantity}.");
                }

                $lineTotal = max(0.00, ($quantity * $unitPrice) - $lineDiscount);
                $subtotal += ($quantity * $unitPrice);

                $itemsPayload[] = [
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'total' => $lineTotal,
                ];
            }

            $discount = (float) ($orderDiscount ?? 0.00);
            $tax = 0.00; // Tax-free or standard rate
            $grandTotal = max(0.00, ($subtotal - $discount) + $tax);

            // 2. Validate tender breakdown
            $cashAmount = (float) ($tenderBreakdown['cash'] ?? 0.00);
            $cardAmount = (float) ($tenderBreakdown['card'] ?? 0.00);
            $khataAmount = (float) ($tenderBreakdown['khata'] ?? 0.00);
            $cashTendered = isset($tenderBreakdown['cash_tendered']) ? (float) $tenderBreakdown['cash_tendered'] : null;
            $changeGiven = $cashTendered !== null && $cashTendered > $cashAmount ? ($cashTendered - $cashAmount) : 0.00;

            $totalTendered = $cashAmount + $cardAmount + $khataAmount;
            if (abs($totalTendered - $grandTotal) > 0.01) {
                throw new Exception("Tender breakdown total (PKR {$totalTendered}) does not match grand total (PKR {$grandTotal}).");
            }

            // 3. Khata (Credit) validation
            if ($khataAmount > 0) {
                if (!$customerId) {
                    throw new Exception('Customer must be specified for Khata / Credit transactions.');
                }
                $customer = $this->customerRepo->findById($tenantId, $customerId);
                if (!$customer) {
                    throw new Exception("Customer ID {$customerId} not found.");
                }

                $currentBalance = $this->customerRepo->getCustomerCurrentBalance($tenantId, $customerId);
                if ($customer->credit_limit > 0 && ($currentBalance + $khataAmount) > $customer->credit_limit) {
                    $excess = ($currentBalance + $khataAmount) - $customer->credit_limit;
                    throw new Exception("Customer credit limit exceeded by PKR {$excess}. Limit: PKR {$customer->credit_limit}.");
                }
            }

            // 4. Generate document numbers
            $today = Carbon::now()->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));
            $invoiceNo = "INV-{$deviceId}-{$today}-{$randomSuffix}";
            $fbrPosId = $tenant->fbr_pos_id ?? 'POS-001';
            $fbrInvoiceNo = "{$fbrPosId}-" . Carbon::now()->timestamp;
            $invoiceUuid = (string) Str::uuid();

            // 5. Persist Invoice
            $invoice = $this->invoiceRepo->createInvoice([
                'uuid' => $invoiceUuid,
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'invoice_no' => $invoiceNo,
                'fbr_invoice_no' => $fbrInvoiceNo,
                'device_id' => $deviceId,
                'cashier_name' => $cashierName,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'cash_amount' => $cashAmount,
                'card_amount' => $cardAmount,
                'khata_amount' => $khataAmount,
                'cash_tendered' => $cashTendered,
                'change_given' => $changeGiven,
                'is_synced' => true,
            ], $itemsPayload);

            // 6. Record append-only stock movement reductions
            foreach ($itemsPayload as $lineItem) {
                $this->inventoryService->adjustStock(
                    tenantId: $tenantId,
                    productId: $lineItem['product_id'],
                    quantity: -1 * $lineItem['quantity'], // Negative quantity for stock deduction
                    type: 'sale',
                    notes: "Sold on Invoice #{$invoiceNo}",
                    referenceType: 'invoice',
                    referenceUuid: $invoiceUuid,
                    deviceId: $deviceId
                );
            }

            // 7. Record Khata ledger entry if credit portion exists
            if ($khataAmount > 0 && $customerId) {
                $this->khataService->postCreditSale(
                    tenantId: $tenantId,
                    customerId: $customerId,
                    amount: $khataAmount,
                    invoiceNo: $invoiceNo,
                    referenceUuid: $invoiceUuid,
                    deviceId: $deviceId
                );
            }

            // 8. Record Outbox change for hybrid cloud sync
            $this->syncOutboxRepo->recordOutbox(
                $tenantId,
                'invoices',
                $invoiceUuid,
                'insert',
                $invoice->toArray(),
                $deviceId
            );

            return $invoice;
        });
    }
}

