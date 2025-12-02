<?php

namespace App\Services;

use App\Models\Hold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoldExpiryService
{

    public function processExpiredHolds(): int
    {
        $processed = 0;

        // Get expired, unused holds in batches
        Hold::where('expires_at', '<=', now())
            ->where('is_used', false)
            ->chunkById(100, function ($holds) use (&$processed) {
                foreach ($holds as $hold) {
                    try {
                        DB::transaction(function () use ($hold, &$processed) {
                            // Re-check in transaction to avoid race conditions
                            $hold = Hold::lockForUpdate()->find($hold->id);

                            if ($hold && !$hold->is_used && $hold->isExpired()) {
                                // Mark as used to prevent double-processing
                                $hold->markAsUsed();

                                // Invalidate cache
                                app(StockService::class)->invalidateStockCache($hold->product);

                                $processed++;

                                Log::info('Expired hold processed (available_stock increased)', [
                                    'hold_id' => $hold->id,
                                    'product_id' => $hold->product_id,
                                    'quantity' => $hold->quantity,
                                    'total_stock' => $hold->product->stock,
                                ]);
                            }
                        });
                    } catch (\Exception $e) {
                        Log::error('Error processing expired hold', [
                            'hold_id' => $hold->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $processed;
    }
}


