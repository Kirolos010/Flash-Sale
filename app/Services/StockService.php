<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    public function getAvailableStock(Product $product)
    {
        $cacheKey = "product:{$product->id}:available_stock";

        return Cache::remember($cacheKey, 60, function () use ($product) {
            $product = Product::lockForUpdate()->find($product->id);

            // Calculate available stock: total_stock - active_holds - pending_orders
            $reservedByHolds = $product->activeHolds()->sum('quantity');
            $reservedByOrders = $product->pendingOrders()->sum('quantity');
            $totalReserved = $reservedByHolds + $reservedByOrders;
            $available = max(0, $product->stock - $totalReserved);

            Log::info('Stock calculated', [
                'product_id' => $product->id,
                'total_stock' => $product->stock,
                'reserved_by_holds' => $reservedByHolds,
                'reserved_by_orders' => $reservedByOrders,
                'total_reserved' => $totalReserved,
                'available_stock' => $available,
            ]);

            return $available;
        });
    }


    public function invalidateStockCache(Product $product)
    {
        Cache::forget("product:{$product->id}:available_stock");
    }


    public function reserveStock(Product $product, int $quantity)
    {
        return DB::transaction(function () use ($product, $quantity) {
            $product = Product::lockForUpdate()->find($product->id);

            if (!$product) {
                return null;
            }

            // Calculate available stock: total_stock - active_holds - pending_orders
            $reservedByHolds = $product->activeHolds()->sum('quantity');
            $reservedByOrders = $product->pendingOrders()->sum('quantity');
            $totalReserved = $reservedByHolds + $reservedByOrders;
            $available = max(0, $product->stock - $totalReserved);

            if ($available < $quantity) {
                Log::warning('Insufficient stock for hold', [
                    'product_id' => $product->id,
                    'requested' => $quantity,
                    'available' => $available,
                    'total_stock' => $product->stock,
                    'reserved_by_holds' => $reservedByHolds,
                    'reserved_by_orders' => $reservedByOrders,
                    'total_reserved' => $totalReserved,
                ]);
                return null;
            }

            // Create hold
            $hold = $product->holds()->create([
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes(2),
                'is_used' => false,
            ]);

            // Invalidate cache
            $this->invalidateStockCache($product);

            Log::info('Hold created (stock reserved temporarily)', [
                'hold_id' => $hold->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'total_stock' => $product->stock,
                'available_stock' => $available - $quantity,
                'reserved_by_holds' => $reservedByHolds + $quantity,
                'reserved_by_orders' => $reservedByOrders,
                'expires_at' => $hold->expires_at,
            ]);

            return $hold;
        }, 5); // Retry up to 5 times on deadlock
    }


    public function decrementStock(Product $product, int $quantity): void
    {
        DB::transaction(function () use ($product, $quantity) {
            $product = Product::lockForUpdate()->find($product->id);

            if ($product) {
                $product->decrement('stock', $quantity);
                $this->invalidateStockCache($product);

                Log::info('Total stock decremented (final deduction)', [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'new_total_stock' => $product->fresh()->stock,
                ]);
            }
        });
    }
}


