<?php

use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/holds', [HoldController::class, 'store']);
Route::post('/orders', [OrderController::class, 'store']);
Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);


