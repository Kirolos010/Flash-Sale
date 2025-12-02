<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'hold_id',
        'product_id',
        'quantity',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function hold(): BelongsTo
    {
        return $this->belongsTo(Hold::class);
    }


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    public function paymentWebhooks(): HasMany
    {
        return $this->hasMany(PaymentWebhook::class);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);

        // Decrement total_stock (final deduction - product is sold)
        app(\App\Services\StockService::class)->decrementStock($this->product, $this->quantity);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);

        // Invalidate cache (available_stock will increase automatically)
        // total_stock doesn't change because it was never decremented
        app(\App\Services\StockService::class)->invalidateStockCache($this->product);
    }
}


