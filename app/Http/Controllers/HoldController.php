<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\CreateHoldRequest;
use App\Http\Resources\HoldResource;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;

class HoldController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    public function store(CreateHoldRequest $request)
    {
        try {
            $validated = $request->validated();
            $product = Product::findOrFail($validated['product_id']);
            $quantity = $validated['qty'];

            $hold = $this->stockService->reserveStock($product, $quantity);

            if (!$hold) {
                return ApiResponse::conflict('Insufficient stock available');
            }

            return ApiResponse::success(
                new HoldResource($hold),
                'Hold created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('Product not found');
        }
    }
}

