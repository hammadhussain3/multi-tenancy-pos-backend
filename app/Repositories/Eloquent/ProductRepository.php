<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function getAllByTenant(int $tenantId, ?string $category = null, ?string $search = null): Collection
    {
        // Eager load stockMovements sum to prevent N+1 query performance bottleneck
        $query = Product::where('tenant_id', $tenantId)
            ->withSum('stockMovements as current_stock', 'quantity');

        if (!empty($category) && $category !== 'All') {
            $query->where('category', $category);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_urdu', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function findById(int $tenantId, int $id): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->withSum('stockMovements as current_stock', 'quantity')
            ->find($id);
    }

    public function findByBarcode(int $tenantId, string $barcode): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->where('barcode', $barcode)
            ->withSum('stockMovements as current_stock', 'quantity')
            ->first();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }
}

