<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function createInvoice(array $invoiceData, array $itemsData): Invoice
    {
        $invoice = Invoice::create($invoiceData);

        foreach ($itemsData as $item) {
            $item['invoice_id'] = $invoice->id;
            InvoiceItem::create($item);
        }

        return $invoice->load(['items', 'customer']);
    }

    public function getAllByTenant(int $tenantId, ?string $search = null): Collection
    {
        $query = Invoice::where('tenant_id', $tenantId)
            ->with(['customer', 'items']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhere('fbr_invoice_no', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findById(int $tenantId, int $id): ?Invoice
    {
        return Invoice::where('tenant_id', $tenantId)
            ->with(['customer', 'items'])
            ->find($id);
    }

    public function getDailySalesSummary(int $tenantId, string $date): array
    {
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_sales' => (float) $invoices->sum('grand_total'),
            'cash_sales' => (float) $invoices->sum('cash_amount'),
            'card_sales' => (float) $invoices->sum('card_amount'),
            'khata_sales' => (float) $invoices->sum('khata_amount'),
        ];
    }
}

