<?php

use App\Models\Product;
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
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class)->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'expires_at', 'is_used']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
