<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function getAllByTenant(int $tenantId, ?string $search = null): Collection
    {
        // Eager load debits and credits sum to calculate running balance without N+1
        $query = Customer::where('tenant_id', $tenantId)
            ->withSum('ledgerEntries as total_debit', 'debit')
            ->withSum('ledgerEntries as total_credit', 'credit');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function findById(int $tenantId, int $id): ?Customer
    {
        return Customer::where('tenant_id', $tenantId)
            ->withSum('ledgerEntries as total_debit', 'debit')
            ->withSum('ledgerEntries as total_credit', 'credit')
            ->find($id);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function recordLedgerEntry(array $data): CustomerLedgerEntry
    {
        return CustomerLedgerEntry::create($data);
    }

    public function getCustomerCurrentBalance(int $tenantId, int $customerId): float
    {
        $debits = (float) CustomerLedgerEntry::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->sum('debit');

        $credits = (float) CustomerLedgerEntry::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->sum('credit');

        return $debits - $credits;
    }

    public function getLedgerHistory(int $tenantId, int $customerId): Collection
    {
        return CustomerLedgerEntry::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}

