<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function getAllByTenant(int $tenantId, ?string $category = null, ?string $search = null): Collection;

    public function findById(int $tenantId, int $id): ?Product;

    public function findByBarcode(int $tenantId, string $barcode): ?Product;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): bool;
}

