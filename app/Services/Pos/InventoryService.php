<?php

namespace App\Services\Pos;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\SyncOutboxRepositoryInterface;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(
        protected StockMovementRepositoryInterface $stockRepo,
        protected SyncOutboxRepositoryInterface $syncOutboxRepo
    ) {}

    /**
     * Appends an immutable stock movement row to the ledger.
     */
    public function adjustStock(
        int $tenantId,
        int $productId,
        int $quantity,
        string $type,
        ?string $notes = null,
        ?string $referenceType = null,
        ?string $referenceUuid = null,
        string $deviceId = 'REG-01'
    ): StockMovement {
        $uuid = (string) Str::uuid();

        $movement = $this->stockRepo->recordMovement([
            'uuid' => $uuid,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_uuid' => $referenceUuid,
            'notes' => $notes,
            'device_id' => $deviceId,
        ]);

        // Record for hybrid sync outbox
        $this->syncOutboxRepo->recordOutbox(
            $tenantId,
            'stock_movements',
            $uuid,
            'insert',
            $movement->toArray(),
            $deviceId
        );

        return $movement;
    }

    public function getStock(int $tenantId, int $productId): int
    {
        return $this->stockRepo->getProductStock($tenantId, $productId);
    }

    public function checkAvailability(int $tenantId, int $productId, int $requestedQty): bool
    {
        $current = $this->getStock($tenantId, $productId);
        return $current >= $requestedQty;
    }
}

