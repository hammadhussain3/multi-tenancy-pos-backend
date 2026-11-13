<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMovement;
use App\Repositories\Contracts\StockMovementRepositoryInterface;

class StockMovementRepository implements StockMovementRepositoryInterface
{
    public function recordMovement(array $data): StockMovement
    {
        return StockMovement::create($data);
    }

    public function getProductStock(int $tenantId, int $productId): int
    {
        return (int) StockMovement::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }
}

