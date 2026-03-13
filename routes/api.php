<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp')->group(function () {
    Route::get('/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhook', [WhatsAppWebhookController::class, 'handle']);
});

Route::prefix('storefront/{store:slug}')->group(function () {
    Route::get('/', [StorefrontController::class, 'show']);
    Route::get('/products', [StorefrontController::class, 'products']);
});

Route::prefix('dashboard/{tenant:slug}')->group(function () {
    Route::get('/overview', [AdminDashboardController::class, 'overview']);
    Route::get('/conversations', [AdminDashboardController::class, 'conversations']);
    Route::get('/orders', [AdminDashboardController::class, 'orders']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
