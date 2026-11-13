<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use Illuminate\Database\Eloquent\Collection;

interface CustomerRepositoryInterface
{
    public function getAllByTenant(int $tenantId, ?string $search = null): Collection;

    public function findById(int $tenantId, int $id): ?Customer;

    public function create(array $data): Customer;

    public function recordLedgerEntry(array $data): CustomerLedgerEntry;

    public function getCustomerCurrentBalance(int $tenantId, int $customerId): float;

    public function getLedgerHistory(int $tenantId, int $customerId): Collection;
}

