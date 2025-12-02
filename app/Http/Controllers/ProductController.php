<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Get product details with accurate available stock.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $availableStock = $this->stockService->getAvailableStock($product);

            $product->setAttribute('available_stock', $availableStock);

            return ApiResponse::success(
                new ProductResource($product),
                'Product retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        }
    }
}

