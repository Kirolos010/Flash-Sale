<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'expires_at',
        'is_used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
        'quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }


    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }
}


