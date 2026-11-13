<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceRepositoryInterface
{
    public function createInvoice(array $invoiceData, array $itemsData): Invoice;

    public function getAllByTenant(int $tenantId, ?string $search = null): Collection;

    public function findById(int $tenantId, int $id): ?Invoice;

    public function getDailySalesSummary(int $tenantId, string $date): array;
}

