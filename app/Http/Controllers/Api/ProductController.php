<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Pos\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductRepositoryInterface $productRepo,
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 1);
        $category = $request->query('category');
        $search = $request->query('search');

        $products = $this->productRepo->getAllByTenant($tenantId, $category, $search);

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 1);
        $product = $this->productRepo->findById($tenantId, $id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $initialStock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock']);

        $product = $this->productRepo->create($data);

        // Record initial stock if provided via append-only inventory movement
        if ($initialStock > 0) {
            $this->inventoryService->adjustStock(
                tenantId: $product->tenant_id,
                productId: $product->id,
                quantity: $initialStock,
                type: 'purchase',
                notes: 'Initial Stock opening balance'
            );
        }

        $fresh = $this->productRepo->findById($product->tenant_id, $product->id);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => new ProductResource($fresh),
        ], 201);
    }

    public function adjustStock(AdjustStockRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->validated('tenant_id');
        $product = $this->productRepo->findById($tenantId, $id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $movement = $this->inventoryService->adjustStock(
            tenantId: $tenantId,
            productId: $product->id,
            quantity: (int) $request->validated('quantity'),
            type: $request->validated('type'),
            notes: $request->validated('notes')
        );

        $fresh = $this->productRepo->findById($tenantId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Stock movement recorded successfully.',
            'movement' => $movement,
            'product' => new ProductResource($fresh),
        ]);
    }

    public function update(CreateProductRequest $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->validated('tenant_id');
        $product = $this->productRepo->findById($tenantId, $id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $data = $request->validated();
        unset($data['initial_stock']);

        $updated = $this->productRepo->update($product, $data);
        $fresh = $this->productRepo->findById($tenantId, $updated->id);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => new ProductResource($fresh),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) $request->query('tenant_id', 1);
        $product = $this->productRepo->findById($tenantId, $id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $this->productRepo->delete($product);

        return response()->json([
            'success' => true,
            'message' => 'Product removed successfully.',
        ]);
    }
}

