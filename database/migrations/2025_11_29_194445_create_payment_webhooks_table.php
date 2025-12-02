<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignIdFor(Order::class)->nullable()->constrained()->onDelete('cascade');
            $table->enum('status', ['success', 'failed']);
            $table->text('payload')->nullable();
            $table->timestamps();

            $table->index('idempotency_key');
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};


