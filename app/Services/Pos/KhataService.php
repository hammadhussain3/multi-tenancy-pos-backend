<?php

namespace App\Services\Pos;

use App\Models\CustomerLedgerEntry;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\SyncOutboxRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Str;

class KhataService
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepo,
        protected SyncOutboxRepositoryInterface $syncOutboxRepo
    ) {}

    /**
     * Posts a credit sale debit to the customer ledger.
     */
    public function postCreditSale(
        int $tenantId,
        int $customerId,
        float $amount,
        string $invoiceNo,
        ?string $referenceUuid = null,
        string $deviceId = 'REG-01'
    ): CustomerLedgerEntry {
        $currentBalance = $this->customerRepo->getCustomerCurrentBalance($tenantId, $customerId);
        $newRunningBalance = $currentBalance + $amount;
        $uuid = (string) Str::uuid();

        $entry = $this->customerRepo->recordLedgerEntry([
            'uuid' => $uuid,
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'entry_date' => Carbon::now(),
            'reference_type' => 'sale',
            'reference_no' => $invoiceNo,
            'reference_uuid' => $referenceUuid,
            'debit' => $amount,
            'credit' => 0.00,
            'running_balance' => $newRunningBalance,
            'narration' => "Credit sale on Invoice #{$invoiceNo}",
            'device_id' => $deviceId,
        ]);

        $this->syncOutboxRepo->recordOutbox(
            $tenantId,
            'customer_ledger_entries',
            $uuid,
            'insert',
            $entry->toArray(),
            $deviceId
        );

        return $entry;
    }

    /**
     * Records a payment received to reduce Udhaar.
     */
    public function receivePayment(
        int $tenantId,
        int $customerId,
        float $amount,
        string $receiptNo,
        string $narration = 'Cash Received',
        string $deviceId = 'REG-01'
    ): CustomerLedgerEntry {
        $currentBalance = $this->customerRepo->getCustomerCurrentBalance($tenantId, $customerId);
        $newRunningBalance = $currentBalance - $amount;
        $uuid = (string) Str::uuid();

        $entry = $this->customerRepo->recordLedgerEntry([
            'uuid' => $uuid,
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'entry_date' => Carbon::now(),
            'reference_type' => 'payment_received',
            'reference_no' => $receiptNo,
            'debit' => 0.00,
            'credit' => $amount,
            'running_balance' => $newRunningBalance,
            'narration' => $narration,
            'device_id' => $deviceId,
        ]);

        $this->syncOutboxRepo->recordOutbox(
            $tenantId,
            'customer_ledger_entries',
            $uuid,
            'insert',
            $entry->toArray(),
            $deviceId
        );

        return $entry;
    }

    public function getBalance(int $tenantId, int $customerId): float
    {
        return $this->customerRepo->getCustomerCurrentBalance($tenantId, $customerId);
    }
}

