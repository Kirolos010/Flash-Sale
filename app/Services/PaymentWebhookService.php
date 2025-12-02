<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{

    public function processWebhook(string $idempotencyKey, string $status, ?int $orderId = null, ?array $payload = null): array
    {
        return DB::transaction(function () use ($idempotencyKey, $status, $orderId, $payload) {
            // Check if we've already processed this webhook
            $existingWebhook = PaymentWebhook::where('idempotency_key', $idempotencyKey)->first();

            if ($existingWebhook) {
                Log::info('Duplicate webhook detected', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_status' => $existingWebhook->status,
                ]);

                return [
                    'processed' => false,
                    'message' => 'Webhook already processed',
                    'webhook' => $existingWebhook,
                ];
            }

            // Create webhook record
            $webhook = PaymentWebhook::create([
                'idempotency_key' => $idempotencyKey,
                'order_id' => $orderId,
                'status' => $status,
                'payload' => $payload,
            ]);

            // If order ID is provided
            if ($orderId) {
                $order = Order::lockForUpdate()->find($orderId);

                if ($order) {
                    if ($status === 'success' && $order->status === 'pending') {
                        $order->markAsPaid();

                        // Invalidate stock cache
                        app(StockService::class)->invalidateStockCache($order->product);


                    } elseif ($status === 'failed' && $order->status === 'pending') {
                        $order->cancel();

                        // Invalidate stock cache
                        app(StockService::class)->invalidateStockCache($order->product);

                        Log::info('Order cancelled via webhook', [
                            'order_id' => $order->id,
                            'idempotency_key' => $idempotencyKey,
                        ]);
                    }
                } else {

                    Log::info('Webhook received before order creation', [
                        'idempotency_key' => $idempotencyKey,
                        'order_id' => $orderId,
                    ]);
                }
            }

            return [
                'processed' => true,
                'webhook' => $webhook,
            ];
        }, 5);
    }

    public function processPendingWebhooksForOrder(Order $order): void
    {

        $webhooks = PaymentWebhook::where('order_id', $order->id)->get();

        foreach ($webhooks as $webhook) {
            $order = Order::lockForUpdate()->find($order->id);

            if ($order && $order->status === 'pending') {
                if ($webhook->status === 'success') {
                    $order->markAsPaid();
                    app(StockService::class)->invalidateStockCache($order->product);

                    Log::info('Order updated from pending webhook', [
                        'order_id' => $order->id,
                        'webhook_id' => $webhook->id,
                    ]);
                } elseif ($webhook->status === 'failed') {
                    $order->cancel();
                    app(StockService::class)->invalidateStockCache($order->product);

                    Log::info('Order cancelled from pending webhook', [
                        'order_id' => $order->id,
                        'webhook_id' => $webhook->id,
                    ]);
                }
            }
        }
    }
}

