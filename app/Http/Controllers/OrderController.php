<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Hold;
use App\Models\Order;
use App\Services\PaymentWebhookService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private PaymentWebhookService $webhookService
    ) {}

    public function store(CreateOrderRequest $request)
    {
        return DB::transaction(function () use ($request) {
            try {
                $validated = $request->validated();
                $hold = Hold::lockForUpdate()->findOrFail($validated['hold_id']);

                if ($hold->is_used) {
                    return ApiResponse::conflict('Hold has already been used');
                }

                if ($hold->isExpired()) {
                    // Mark as used to prevent reuse
                    $hold->markAsUsed();

                    // Invalidate cache (available_stock will increase automatically)
                    $this->stockService->invalidateStockCache($hold->product);

                    return ApiResponse::conflict('Hold has expired');
                }

                if ($hold->order) {
                    return ApiResponse::conflict('Order already exists for this hold');
                }

                // Create order
                $order = Order::create([
                    'hold_id' => $hold->id,
                    'product_id' => $hold->product_id,
                    'quantity' => $hold->quantity,
                    'total_amount' => $hold->product->price * $hold->quantity,
                    'status' => 'pending',
                ]);

                // Mark hold as used
                $hold->markAsUsed();

                $this->stockService->invalidateStockCache($hold->product);

                $this->webhookService->processPendingWebhooksForOrder($order);

                $order->refresh();

                return ApiResponse::success(
                    new OrderResource($order),
                    'Order created successfully',
                    201
                );
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return ApiResponse::notFound('Hold not found');
            }
        }, 5);
    }
}

