<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeHolds(): HasMany
    {
        return $this->holds()
            ->where('expires_at', '>', now())
            ->where('is_used', false);
    }


    public function pendingOrders(): HasMany
    {
        return $this->orders()
            ->where('status', 'pending');
    }

    public function getAvailableStockAttribute(): int
    {
        $reservedByHolds = $this->activeHolds()->sum('quantity');
        $reservedByOrders = $this->pendingOrders()->sum('quantity');
        $totalReserved = $reservedByHolds + $reservedByOrders;

        return max(0, $this->stock - $totalReserved);
    }

    public function available(): int
    {
        $key = "product:{$this->id}:available";
        return Cache::remember($key, 10, function () {
        $activeHolds = $this->holds()->where('expires_at', '>', Carbon::now())->sum('qty');
        $paid = $this->orders()->where('status', 'paid')->sum('qty');
        return max(0, $this->stock_total - $activeHolds - $paid);
        });
    }


    public function clearAvailableCache()
    {
    Cache::forget("product:{$this->id}:available");
    }
}


