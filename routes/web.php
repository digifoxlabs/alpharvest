<?php

use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\AdminCategoryController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminMessageController;
use App\Http\Controllers\Web\AdminProductController;
use App\Http\Controllers\Web\AdminStoreController;
use App\Http\Controllers\Web\AdminTenantController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\TenantOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlatformController::class, 'home'])->name('platform.home');
Route::get('/dashboard/{tenant:slug}', [PlatformController::class, 'dashboard'])->name('dashboard.show');
Route::post('/dashboard/{tenant:slug}/orders/{order}/request-address', [TenantOrderController::class, 'requestAddress'])->name('dashboard.orders.request-address');
Route::post('/dashboard/{tenant:slug}/orders/{order}/send-payment-link', [TenantOrderController::class, 'sendPaymentLink'])->name('dashboard.orders.send-payment-link');
Route::get('/stores/{store:slug}/products/{product}', [PlatformController::class, 'product'])->name('platform.products.show');
Route::get('/feeds/meta-products', [FeedController::class, 'metaProducts'])->name('feeds.meta-products');
Route::get('/feeds/meta-placeholder.svg', [FeedController::class, 'metaPlaceholder'])->name('feeds.meta-placeholder');
Route::get('/pay/{payment:reference}', [CheckoutController::class, 'show'])->name('payments.show');
Route::post('/pay/{payment:reference}/confirm', [CheckoutController::class, 'confirm'])->name('payments.confirm');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant:slug}/edit', [AdminTenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant:slug}', [AdminTenantController::class, 'update'])->name('tenants.update');
    Route::delete('/tenants/{tenant:slug}', [AdminTenantController::class, 'destroy'])->name('tenants.destroy');

    Route::get('/stores', [AdminStoreController::class, 'index'])->name('stores.index');
    Route::post('/stores', [AdminStoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store:slug}/edit', [AdminStoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store:slug}', [AdminStoreController::class, 'update'])->name('stores.update');
    Route::delete('/stores/{store:slug}', [AdminStoreController::class, 'destroy'])->name('stores.destroy');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
});
