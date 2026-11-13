<?php

namespace App\Repositories\Contracts;

use App\Models\StockMovement;

interface StockMovementRepositoryInterface
{
    public function recordMovement(array $data): StockMovement;

    public function getProductStock(int $tenantId, int $productId): int;
}

