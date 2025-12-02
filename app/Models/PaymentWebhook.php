<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'order_id',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];


    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}


