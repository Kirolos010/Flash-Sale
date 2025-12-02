<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\PaymentWebhookRequest;
use App\Http\Resources\PaymentWebhookResource;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentWebhookService $webhookService
    ) {}


    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->webhookService->processWebhook(
            idempotencyKey: $validated['idempotency_key'],
            status: $validated['status'],
            orderId: $validated['order_id'] ?? null,
            payload: $validated['payload'] ?? null
        );

        if (!$result['processed']) {
            return ApiResponse::success(
                ['webhook' => new PaymentWebhookResource($result['webhook'])],
                $result['message']
            );
        }

        return ApiResponse::success(
            ['webhook' => new PaymentWebhookResource($result['webhook'])],
            'Webhook processed successfully'
        );
    }
}

